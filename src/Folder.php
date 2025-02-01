<?php

namespace Webklex\PHPIMAP;

use Carbon\Carbon;
use Webklex\PHPIMAP\Exceptions\NotSupportedCapabilityException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\FolderCollection;
use Webklex\PHPIMAP\Traits\HasEvents;

class Folder
{
    use HasEvents;

    /**
     * Client instance.
     */
    protected Client $client;

    /**
     * Folder full path.
     */
    public string $path;

    /**
     * Folder name.
     */
    public string $name;

    /**
     * Folder full name.
     */
    public string $fullName;

    /**
     * Children folders.
     */
    public FolderCollection $children;

    /**
     * Delimiter for folder.
     */
    public string $delimiter;

    /**
     * Indicates if folder can't contain any "children".
     * CreateFolder won't work on this folder.
     */
    public bool $noInferiors;

    /**
     * Indicates if folder is only container, not a mailbox - you can't open it.
     */
    public bool $noSelect;

    /**
     * Indicates if folder is marked. This means that it may contain new messages since the last time it was checked.
     * Not provided by all IMAP servers.
     */
    public bool $marked;

    /**
     * Indicates if folder contains any "children".
     * Not provided by all IMAP servers.
     */
    public bool $hasChildren;

    /**
     * Indicates if folder refers to others.
     * Not provided by all IMAP servers.
     */
    public bool $referral;

    /**
     * The folder status information.
     */
    public array $status;

    /**
     * The folder capabilities.
     */
    protected ?array $capabilities = null;

    /**
     * Folder constructor.
     *
     * @param  string[]  $attributes
     */
    public function __construct(Client $client, string $folderName, string $delimiter, array $attributes)
    {
        $this->client = $client;

        $this->events['message'] = $client->getDefaultEvents('message');
        $this->events['folder'] = $client->getDefaultEvents('folder');

        $this->setDelimiter($delimiter);

        $this->path = $folderName;
        $this->hasChildren = false;
        $this->children = new FolderCollection;
        $this->fullName = $this->decodeName($folderName);
        $this->name = $this->getSimpleName($this->delimiter, $this->fullName);

        $this->parseAttributes($attributes);
    }

    /**
     * Get a new search query instance.
     *
     * @param  string[]  $extensions
     */
    public function query(array $extensions = []): WhereQuery
    {
        $this->client->checkConnection();

        $this->client->openFolder($this->path);

        $extensions = count($extensions) > 0
            ? $extensions
            : $this->client->extensions;

        return new WhereQuery($this->client, $extensions);
    }

    /**
     * Get a new search query instance.
     *
     * @param  string[]  $extensions
     */
    public function search(array $extensions = []): WhereQuery
    {
        return $this->query($extensions);
    }

    /**
     * Get a new search query instance.
     *
     * @param  string[]  $extensions
     */
    public function messages(array $extensions = []): WhereQuery
    {
        return $this->query($extensions);
    }

    /**
     * Determine if folder has children.
     */
    public function hasChildren(): bool
    {
        return $this->hasChildren;
    }

    /**
     * Set children.
     */
    public function setChildren(FolderCollection $children): Folder
    {
        $this->children = $children;

        return $this;
    }

    /**
     * Get children.
     */
    public function getChildren(): FolderCollection
    {
        return $this->children;
    }

    /**
     * Decode name. Converts UTF7-IMAP encoding to UTF-8.
     */
    protected function decodeName(mixed $name): string|array|bool|null
    {
        $parts = [];

        foreach (explode($this->delimiter, $name) as $item) {
            $parts[] = EncodingAliases::convert($item, 'UTF7-IMAP');
        }

        return implode($this->delimiter, $parts);
    }

    /**
     * Get simple name (without parent folders).
     */
    protected function getSimpleName(string $delimiter, string $fullName): string|bool
    {
        $arr = explode($delimiter, $fullName);

        return end($arr);
    }

    /**
     * Parse attributes and set it to object properties.
     */
    protected function parseAttributes(array $attributes): void
    {
        $this->noInferiors = in_array('\NoInferiors', $attributes);
        $this->noSelect = in_array('\NoSelect', $attributes);
        $this->marked = in_array('\Marked', $attributes);
        $this->referral = in_array('\Referral', $attributes);
        $this->hasChildren = in_array('\HasChildren', $attributes);
    }

    /**
     * Move or rename the current folder.
     */
    public function move(string $newName, bool $expunge = true): array
    {
        $this->client->checkConnection();

        $status = $this->client->getConnection()
            ->renameFolder($this->fullName, $newName)
            ->getValidatedData();

        if ($expunge) {
            $this->client->expunge();
        }

        $folder = $this->client->getFolder($newName);

        $this->dispatch('folder', 'moved', $this, $folder);

        return $status;
    }

    /**
     * Get a message overview.
     */
    public function overview(?string $sequence = null): array
    {
        $this->client->openFolder($this->path);

        $sequence = is_null($sequence) ? '1:*' : $sequence;

        $uid = ClientContainer::get('options.sequence', Imap::ST_MSGN);

        $response = $this->client->getConnection()->overview($sequence, $uid);

        return $response->getValidatedData();
    }

    /**
     * Append a string message to the current mailbox.
     */
    public function appendMessage(string $message, ?array $options = null, Carbon|string|null $internalDate = null): array
    {
        // Check if $internal_date is parsed. If it is null it should not be set. Otherwise, the message can't be stored.
        // If this parameter is set, it will set the INTERNALDATE on the appended message. The parameter should be a
        // date string that conforms to the rfc2060 specifications for a date_time value or be a Carbon object.
        if ($internalDate instanceof Carbon) {
            $internalDate = $internalDate->format('d-M-Y H:i:s O');
        }

        return $this->client->getConnection()
            ->appendMessage($this->path, $message, $options, $internalDate)
            ->getValidatedData();
    }

    /**
     * Rename the current folder.
     */
    public function rename(string $newName, bool $expunge = true): array
    {
        return $this->move($newName, $expunge);
    }

    /**
     * Delete the current folder.
     */
    public function delete(bool $expunge = true): array
    {
        $status = $this->client->getConnection()
            ->deleteFolder($this->path)
            ->getValidatedData();

        if ($this->client->getActiveFolder() == $this->path) {
            $this->client->setActiveFolder();
        }

        if ($expunge) {
            $this->client->expunge();
        }

        $this->dispatch('folder', 'deleted', $this);

        return $status;
    }

    /**
     * Subscribe the current folder.
     */
    public function subscribe(): array
    {
        $this->client->openFolder($this->path);

        return $this->client->getConnection()
            ->subscribeFolder($this->path)
            ->getValidatedData();
    }

    /**
     * Unsubscribe the current folder.
     */
    public function unsubscribe(): array
    {
        $this->client->openFolder($this->path);

        return $this->client->getConnection()
            ->unsubscribeFolder($this->path)
            ->getValidatedData();
    }

    /**
     * Begin idling on the current folder.
     */
    public function idle(callable $callback, int $timeout = 300): void
    {
        if (! $this->hasIdleSupport()) {
            throw new NotSupportedCapabilityException('IMAP server does not support IDLE');
        }

        $fetch = function (int $msgn, int $sequence) {
            // Always reopen the folder on the main client.
            // Otherwise, the new message number isn't
            // known to the current remote session.
            $this->client->openFolder($this->path, true);

            $message = $this->query()->getMessageByMsgn($msgn);

            $message->setSequence($sequence);

            return $message;
        };

        (new Idle(clone $this->client, $this->path, $timeout))->await(
            function (int $msgn, int $sequence) use ($callback, $fetch) {
                // Connect the client if the connection is closed.
                if ($this->client->isClosed()) {
                    $this->client->connect();
                }

                try {
                    $message = $fetch($msgn, $sequence);
                } catch (RuntimeException|ResponseException) {
                    // If fetching the message fails, we'll attempt
                    // reconnecting and re-fetching the message.
                    $this->client->reconnect();

                    $message = $fetch($msgn, $sequence);
                }

                $callback($message);

                $this->dispatch('message', 'new', $message);
            }
        );
    }

    /**
     * Get folder status information from the EXAMINE command.
     */
    public function status(): array
    {
        return $this->client->getConnection()
            ->folderStatus($this->path)
            ->getValidatedData();
    }

    /**
     * Get folder status information from the EXAMINE command.
     *
     * @deprecated Use Folder::status() instead
     */
    public function getStatus(): array
    {
        return $this->status();
    }

    /**
     * Load folder status information from the EXAMINE command.
     */
    public function loadStatus(): Folder
    {
        $this->status = $this->examine();

        return $this;
    }

    /**
     * Examine the current folder.
     */
    public function examine(): array
    {
        return $this->client->getConnection()
            ->examineFolder($this->path)
            ->getValidatedData();
    }

    /**
     * Select the current folder.
     */
    public function select(): array
    {
        return $this->client->getConnection()
            ->selectFolder($this->path)
            ->getValidatedData();
    }

    /**
     * Get the current Client instance.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Set the delimiter.
     */
    public function setDelimiter(?string $delimiter): void
    {
        if (in_array($delimiter, [null, '', ' ', false]) === true) {
            $delimiter = ClientContainer::get('options.delimiter', '/');
        }

        $this->delimiter = $delimiter;
    }

    /**
     * Determine if the current connection has IDLE support.
     */
    protected function hasIdleSupport(): bool
    {
        return in_array('IDLE', $this->getConnectionCapabilities());
    }

    /**
     * Get the connection's capabilities.
     */
    protected function getConnectionCapabilities(): array
    {
        return $this->capabilities ??= $this->client->getConnection()
            ->getCapabilities()
            ->getValidatedData();
    }
}
