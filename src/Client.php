<?php

namespace Webklex\PHPIMAP;

use ErrorException;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Connection\Protocols\ProtocolInterface;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;
use Webklex\PHPIMAP\Exceptions\MaskNotFoundException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Support\FolderCollection;
use Webklex\PHPIMAP\Support\Masks\AttachmentMask;
use Webklex\PHPIMAP\Support\Masks\MessageMask;
use Webklex\PHPIMAP\Traits\HasEvents;

class Client
{
    use HasEvents;

    /**
     * The server hostname.
     */
    public string $host;

    /**
     * The server port.
     */
    public int $port;

    /**
     * The encryption type (none, ssl, tls, starttls or notls).
     */
    public string $encryption;

    /**
     * Whether the server has to validate cert.
     */
    public bool $validateCert = true;

    /**
     * The proxy settings.
     */
    protected array $proxy = [
        'socket' => null,
        'request_fulluri' => false,
        'username' => null,
        'password' => null,
    ];

    /**
     * The connection timeout.
     */
    public int $timeout;

    /**
     * The account username.
     */
    public string $username;

    /**
     * The account password.
     */
    public string $password;

    /**
     * The additional data fetched from the server.
     */
    public array $extensions;

    /**
     * The account authentication method.
     */
    public ?string $authentication;

    /**
     * The active folder path.
     */
    protected ?string $activeFolder = null;

    /**
     * The default message mask.
     */
    protected string $defaultMessageMask = MessageMask::class;

    /**
     * The default attachment mask.
     */
    protected string $defaultAttachmentMask = AttachmentMask::class;

    /**
     * The default account configuration.
     */
    protected array $defaultAccountConfig = [
        'host' => 'localhost',
        'port' => 993,
        'encryption' => 'ssl',
        'validate_cert' => true,
        'username' => '',
        'password' => '',
        'authentication' => null,
        'extensions' => [],
        'proxy' => [
            'socket' => null,
            'request_fulluri' => false,
            'username' => null,
            'password' => null,
        ],
        'timeout' => 30,
    ];

    /**
     * The underlying connection resource.
     */
    public ?ProtocolInterface $connection = null;

    /**
     * Constructor.
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
        $this->setMaskFromConfig($config);
        $this->setEventsFromConfig($config);
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Clone the instance.
     */
    public function __clone()
    {
        $this->connection = null;
    }

    /**
     * Set the Client configuration.
     */
    public function setConfig(array $config): Client
    {
        $defaultAccount = ClientManager::get('default');
        $defaultConfig = ClientManager::get("accounts.$defaultAccount", []);

        foreach ($this->defaultAccountConfig as $key => $value) {
            $this->setAccountConfig($key, $config, $defaultConfig);
        }

        return $this;
    }

    /**
     * Get the current config.
     */
    public function getConfig(): array
    {
        $config = [];

        foreach ($this->defaultAccountConfig as $key => $value) {
            if (property_exists($this, $property = Str::camel($key))) {
                $config[$key] = $this->{$property};
            }
        }

        return $config;
    }

    /**
     * Set a specific account config.
     */
    protected function setAccountConfig(string $key, array $config, array $defaultConfig): void
    {
        $value = $this->defaultAccountConfig[$key];

        if (isset($config[$key])) {
            $value = $config[$key];
        } elseif (isset($defaultConfig[$key])) {
            $value = $defaultConfig[$key];
        }

        if (property_exists($this, $property = Str::camel($key))) {
            $this->{$property} = $value;
        }
    }

    /**
     * Get the current account config.
     */
    public function getAccountConfig(): array
    {
        $config = [];

        foreach ($this->defaultAccountConfig as $key => $value) {
            if (property_exists($this, $property = Str::camel($key))) {
                $config[$key] = $this->{$property};
            }
        }

        return $config;
    }

    /**
     * Look for a possible events in any available config.
     */
    protected function setEventsFromConfig(array $config): void
    {
        $this->events = ClientManager::get('events', []);

        if (! isset($config['events'])) {
            return;
        }

        foreach ($config['events'] as $section => $events) {
            $this->events[$section] = array_merge($this->events[$section], $events);
        }
    }

    /**
     * Set the default mask from the given config.
     */
    protected function setMaskFromConfig(array $config): void
    {
        if (! isset($config['masks'])) {
            $this->defaultMessageMask = $this->getDefaultMask('message', $this->defaultMessageMask);
            $this->defaultAttachmentMask = $this->getDefaultMask('attachment', $this->defaultAttachmentMask);

            return;
        }

        $this->defaultMessageMask = $this->getMaskFromConfig($config['masks'], 'message');
        $this->defaultAttachmentMask = $this->getMaskFromConfig($config['masks'], 'attachment');
    }

    /**
     * Get a mask from the given configured masks.
     */
    protected function getMaskFromConfig(array $masks, string $type): string
    {
        if (! class_exists($mask = $masks[$type])) {
            throw new MaskNotFoundException("Unknown mask provided: {$mask}");
        }

        return $mask;
    }

    /**
     * Load a default mask from the ClientManager, or throw if none is set.
     */
    protected function getDefaultMask(string $type, string $default): string
    {
        return ClientManager::getMask($type) ?? $default;
    }

    /**
     * Get the current imap resource.
     */
    public function getConnection(): ProtocolInterface
    {
        $this->checkConnection();

        return $this->connection;
    }

    /**
     * Determine if connection was established.
     */
    public function isConnected(): bool
    {
        return $this->connection && $this->connection->connected();
    }

    /**
     * Determine if the connection is closed.
     */
    public function isClosed(): bool
    {
        if (! $this->isConnected()) {
            return true;
        }

        $meta = $this->connection->meta();

        return $meta['timed_out'] || $meta['eof'];
    }

    /**
     * Determine if connection was established and connect if not.
     */
    public function checkConnection(): bool
    {
        try {
            if (! $this->isConnected()) {
                $this->connect();

                return true;
            }
        } catch (Throwable) {
            $this->connect();
        }

        return false;
    }

    /**
     * Force the connection to reconnect.
     */
    public function reconnect(): void
    {
        if ($this->isConnected()) {
            $this->disconnect();
        }

        $this->connect();
    }

    /**
     * Connect to server.
     */
    public function connect(): Client
    {
        $this->disconnect();

        $this->connection = new ImapProtocol($this->validateCert, $this->encryption);
        $this->connection->setConnectionTimeout($this->timeout);
        $this->connection->setProxy($this->proxy);

        if (ClientManager::get('options.debug')) {
            $this->connection->enableDebug();
        }

        if (! ClientManager::get('options.uid_cache')) {
            $this->connection->disableUidCache();
        }

        try {
            $this->connection->connect($this->host, $this->port);
        } catch (ErrorException|RuntimeException $e) {
            throw new ConnectionFailedException('connection setup failed', 0, $e);
        }

        $this->authenticate();

        return $this;
    }

    /**
     * Authenticate the current session.
     */
    protected function authenticate(): void
    {
        if ($this->authentication == 'oauth') {
            if (! $this->connection->authenticate($this->username, $this->password)->getValidatedData()) {
                throw new AuthFailedException;
            }
        } elseif (! $this->connection->login($this->username, $this->password)->getValidatedData()) {
            throw new AuthFailedException;
        }
    }

    /**
     * Disconnect from server.
     */
    public function disconnect(): Client
    {
        if ($this->isConnected()) {
            $this->connection->logout();
        }

        $this->activeFolder = null;

        return $this;
    }

    /**
     * Get a folder instance by a folder name.
     */
    public function getFolder(string $folderName, ?string $delimiter = null, bool $utf7 = false): ?Folder
    {
        // Set delimiter to false to force selection via getFolderByName (maybe useful for uncommon folder names)
        $delimiter = is_null($delimiter) ? ClientManager::get('options.delimiter', '/') : $delimiter;

        if (str_contains($folderName, (string) $delimiter)) {
            return $this->getFolderByPath($folderName, $utf7);
        }

        return $this->getFolderByName($folderName);
    }

    /**
     * Get a folder instance by a folder name.
     *
     * @param  bool  $softFail  If true, it will return null instead of throwing an exception
     */
    public function getFolderByName($folderName, bool $softFail = false): ?Folder
    {
        return $this->getFolders(false, null, $softFail)
            ->where('name', $folderName)
            ->first();
    }

    /**
     * Get a folder instance by a folder path.
     *
     * @param  bool  $softFail  If true, it will return null instead of throwing an exception
     */
    public function getFolderByPath($folderPath, bool $utf7 = false, bool $softFail = false): ?Folder
    {
        if (! $utf7) {
            $folderPath = EncodingAliases::convert($folderPath, 'utf-8', 'utf7-imap');
        }

        return $this->getFolders(false, null, $softFail)
            ->where('path', $folderPath)
            ->first();
    }

    /**
     * Get folders list.
     * If hierarchical order is set to true, it will make a tree of folders, otherwise it will return flat array.
     *
     * @param  bool  $softFail  If true, it will return an empty collection instead of throwing an exception
     */
    public function getFolders(bool $hierarchical = true, ?string $parentFolder = null, bool $softFail = false): FolderCollection
    {
        $this->checkConnection();

        $folders = FolderCollection::make();

        $pattern = $parentFolder.($hierarchical ? '%' : '*');
        $items = $this->connection->folders('', $pattern)->getValidatedData();

        if (! empty($items)) {
            foreach ($items as $folder_name => $item) {
                $folder = new Folder($this, $folder_name, $item['delimiter'], $item['flags']);

                if ($hierarchical && $folder->hasChildren()) {
                    $pattern = $folder->full_name.$folder->delimiter.'%';

                    $children = $this->getFolders(true, $pattern, $softFail);
                    $folder->setChildren($children);
                }

                $folders->push($folder);
            }

            return $folders;
        } elseif (! $softFail) {
            throw new FolderFetchingException('Failed to fetch any folders');
        }

        return $folders;
    }

    /**
     * Get folders list.
     * If hierarchical order is set to true, it will make a tree of folders, otherwise it will return flat array.
     *
     * @param  bool  $softFail  If true, it will return an empty collection instead of throwing an exception
     */
    public function getFoldersWithStatus(bool $hierarchical = true, ?string $parentFolder = null, bool $softFail = false): FolderCollection
    {
        $this->checkConnection();

        $folders = FolderCollection::make();

        $pattern = $parentFolder.($hierarchical ? '%' : '*');
        $items = $this->connection->folders('', $pattern)->getValidatedData();

        if (! empty($items)) {
            foreach ($items as $folderName => $item) {
                $folder = new Folder($this, $folderName, $item['delimiter'], $item['flags']);

                if ($hierarchical && $folder->hasChildren()) {
                    $pattern = $folder->full_name.$folder->delimiter.'%';

                    $children = $this->getFoldersWithStatus(true, $pattern, $softFail);
                    $folder->setChildren($children);
                }

                $folder->loadStatus();
                $folders->push($folder);
            }

            return $folders;
        } elseif (! $softFail) {
            throw new FolderFetchingException('Failed to fetch any folders');
        }

        return $folders;
    }

    /**
     * Open a given folder.
     */
    public function openFolder(string $folder_path, bool $force_select = false): array
    {
        if ($this->activeFolder == $folder_path && $this->isConnected() && $force_select === false) {
            return [];
        }

        $this->checkConnection();

        $this->activeFolder = $folder_path;

        return $this->connection->selectFolder($folder_path)->getValidatedData();
    }

    /**
     * Set active folder.
     */
    public function setActiveFolder(?string $folder_path = null): void
    {
        $this->activeFolder = $folder_path;
    }

    /**
     * Get active folder.
     */
    public function getActiveFolder(): ?string
    {
        return $this->activeFolder;
    }

    /**
     * Create a new Folder.
     */
    public function createFolder(string $folder_path, bool $expunge = true, bool $utf7 = false): Folder
    {
        $this->checkConnection();

        if (! $utf7) {
            $folder_path = EncodingAliases::convert($folder_path, 'utf-8', 'UTF7-IMAP');
        }

        $status = $this->connection->createFolder($folder_path)->getValidatedData();

        if ($expunge) {
            $this->expunge();
        }

        $folder = $this->getFolderByPath($folder_path, true);

        if ($status && $folder) {
            $this->dispatch('folder', 'new', $folder);
        }

        return $folder;
    }

    /**
     * Delete a given folder.
     */
    public function deleteFolder(string $folder_path, bool $expunge = true): array
    {
        $this->checkConnection();

        $folder = $this->getFolderByPath($folder_path);

        if ($this->activeFolder == $folder->path) {
            $this->activeFolder = null;
        }

        $status = $this->getConnection()->deleteFolder($folder->path)->getValidatedData();

        if ($expunge) {
            $this->expunge();
        }

        $this->dispatch('folder', 'deleted', $folder);

        return $status;
    }

    /**
     * Check a given folder.
     */
    public function checkFolder(string $folder_path): array
    {
        $this->checkConnection();

        return $this->connection->examineFolder($folder_path)->getValidatedData();
    }

    /**
     * Get the current active folder.
     */
    public function getFolderPath(): ?string
    {
        return $this->activeFolder;
    }

    /**
     * Exchange identification information
     *
     * @see https://datatracker.ietf.org/doc/html/rfc2971
     */
    public function Id(?array $ids = null): array
    {
        $this->checkConnection();

        return $this->connection->id($ids)->getValidatedData();
    }

    /**
     * Retrieve the quota level settings, and usage statics per mailbox.
     */
    public function getQuota(): array
    {
        $this->checkConnection();

        return $this->connection->getQuota($this->username)->getValidatedData();
    }

    /**
     * Retrieve the quota settings per user.
     */
    public function getQuotaRoot(string $quota_root = 'INBOX'): array
    {
        $this->checkConnection();

        return $this->connection->getQuotaRoot($quota_root)->getValidatedData();
    }

    /**
     * Delete all messages marked for deletion.
     */
    public function expunge(): array
    {
        $this->checkConnection();

        return $this->connection->expunge()->getValidatedData();
    }

    /**
     * Set the connection timeout.
     */
    public function setTimeout(int $timeout): ProtocolInterface
    {
        $this->timeout = $timeout;

        if ($this->isConnected()) {
            $this->connection->setConnectionTimeout($timeout);

            $this->reconnect();
        }

        return $this->connection;
    }

    /**
     * Get the connection timeout.
     */
    public function getTimeout(): int
    {
        $this->checkConnection();

        return $this->connection->getConnectionTimeout();
    }

    /**
     * Get the default message mask.
     */
    public function getDefaultMessageMask(): string
    {
        return $this->defaultMessageMask;
    }

    /**
     * Get the default events for a given section.
     */
    public function getDefaultEvents($section): array
    {
        if (isset($this->events[$section])) {
            return is_array($this->events[$section]) ? $this->events[$section] : [];
        }

        return [];
    }

    /**
     * Set the default message mask.
     *
     * @throws MaskNotFoundException
     */
    public function setDefaultMessageMask(string $mask): Client
    {
        if (class_exists($mask)) {
            $this->defaultMessageMask = $mask;

            return $this;
        }

        throw new MaskNotFoundException('Unknown mask provided: '.$mask);
    }

    /**
     * Get the default attachment mask.
     */
    public function getDefaultAttachmentMask(): string
    {
        return $this->defaultAttachmentMask;
    }

    /**
     * Set the default attachment mask.
     *
     * @throws MaskNotFoundException
     */
    public function setDefaultAttachmentMask(string $mask): Client
    {
        if (class_exists($mask)) {
            $this->defaultAttachmentMask = $mask;

            return $this;
        }

        throw new MaskNotFoundException('Unknown mask provided: '.$mask);
    }
}
