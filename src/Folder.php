<?php

namespace Webklex\PHPIMAP;

use Carbon\Carbon;
use Webklex\PHPIMAP\Connection\Protocols\Response;
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
    public string $full_name;

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
    public bool $no_inferiors;

    /**
     * Indicates if folder is only container, not a mailbox - you can't open it.
     */
    public bool $no_select;

    /**
     * Indicates if folder is marked. This means that it may contain new messages since the last time it was checked.
     * Not provided by all IMAP servers.
     */
    public bool $marked;

    /**
     * Indicates if folder contains any "children".
     * Not provided by all IMAP servers.
     */
    public bool $has_children;

    /**
     * Indicates if folder refers to others.
     * Not provided by all IMAP servers.
     */
    public bool $referral;

    /**
     * Folder status information.
     */
    public array $status;

    /**
     * Folder constructor.
     *
     * @param  string[]  $attributes
     */
    public function __construct(Client $client, string $folder_name, string $delimiter, array $attributes)
    {
        $this->client = $client;

        $this->events['message'] = $client->getDefaultEvents('message');
        $this->events['folder'] = $client->getDefaultEvents('folder');

        $this->setDelimiter($delimiter);

        $this->path = $folder_name;
        $this->has_children = false;
        $this->children = new FolderCollection;
        $this->full_name = $this->decodeName($folder_name);
        $this->name = $this->getSimpleName($this->delimiter, $this->full_name);

        $this->parseAttributes($attributes);
    }

    /**
     * Get a new search query instance.
     *
     * @param  string[]  $extensions
     */
    public function query(array $extensions = []): WhereQuery
    {
        $this->getClient()->checkConnection();

        $this->getClient()->openFolder($this->path);

        $extensions = count($extensions) > 0
            ? $extensions
            : $this->getClient()->extensions;

        return new WhereQuery($this->getClient(), $extensions);
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
        return $this->has_children;
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
     *
     * @return string|array|bool|string[]|null
     */
    protected function decodeName($name): string|array|bool|null
    {
        $parts = [];
        foreach (explode($this->delimiter, $name) as $item) {
            $parts[] = EncodingAliases::convert($item, 'UTF7-IMAP', 'UTF-8');
        }

        return implode($this->delimiter, $parts);
    }

    /**
     * Get simple name (without parent folders).
     */
    protected function getSimpleName($delimiter, $full_name): string|bool
    {
        $arr = explode($delimiter, $full_name);

        return end($arr);
    }

    /**
     * Parse attributes and set it to object properties.
     */
    protected function parseAttributes($attributes): void
    {
        $this->no_inferiors = in_array('\NoInferiors', $attributes);
        $this->no_select = in_array('\NoSelect', $attributes);
        $this->marked = in_array('\Marked', $attributes);
        $this->referral = in_array('\Referral', $attributes);
        $this->has_children = in_array('\HasChildren', $attributes);
    }

    /**
     * Move or rename the current folder.
     */
    public function move(string $new_name, bool $expunge = true): array
    {
        $this->client->checkConnection();

        $status = $this->client->getConnection()->renameFolder($this->full_name, $new_name)->validatedData();

        if ($expunge) {
            $this->client->expunge();
        }

        $folder = $this->client->getFolder($new_name);

        $event = $this->getEvent('folder', 'moved');

        $event::dispatch($this, $folder);

        return $status;
    }

    /**
     * Get a message overview.
     */
    public function overview(?string $sequence = null): array
    {
        $this->client->openFolder($this->path);

        $sequence = $sequence === null ? '1:*' : $sequence;

        $uid = ClientManager::get('options.sequence', IMAP::ST_MSGN);

        $response = $this->client->getConnection()->overview($sequence, $uid);

        return $response->validatedData();
    }

    /**
     * Append a string message to the current mailbox.
     */
    public function appendMessage(string $message, ?array $options = null, Carbon|string|null $internal_date = null): array
    {
        // Check if $internal_date is parsed. If it is null it should not be set. Otherwise, the message can't be stored.
        // If this parameter is set, it will set the INTERNALDATE on the appended message. The parameter should be a
        // date string that conforms to the rfc2060 specifications for a date_time value or be a Carbon object.
        if ($internal_date instanceof Carbon) {
            $internal_date = $internal_date->format('d-M-Y H:i:s O');
        }

        return $this->client->getConnection()->appendMessage($this->path, $message, $options, $internal_date)->validatedData();
    }

    /**
     * Rename the current folder.
     */
    public function rename(string $new_name, bool $expunge = true): array
    {
        return $this->move($new_name, $expunge);
    }

    /**
     * Delete the current folder.
     */
    public function delete(bool $expunge = true): array
    {
        $status = $this->client->getConnection()->deleteFolder($this->path)->validatedData();
        if ($this->client->getActiveFolder() == $this->path) {
            $this->client->setActiveFolder(null);
        }

        if ($expunge) {
            $this->client->expunge();
        }

        $event = $this->getEvent('folder', 'deleted');
        $event::dispatch($this);

        return $status;
    }

    /**
     * Subscribe the current folder.
     */
    public function subscribe(): array
    {
        $this->client->openFolder($this->path);

        return $this->client->getConnection()->subscribeFolder($this->path)->validatedData();
    }

    /**
     * Unsubscribe the current folder.
     */
    public function unsubscribe(): array
    {
        $this->client->openFolder($this->path);

        return $this->client->getConnection()->unsubscribeFolder($this->path)->validatedData();
    }

    /**
     * Idle the current connection.
     */
    public function idle(callable $callback, int $timeout = 300): void
    {
        $this->client->setTimeout($timeout);

        if (! in_array('IDLE', $this->client->getConnection()->getCapabilities()->validatedData())) {
            throw new Exceptions\NotSupportedCapabilityException('IMAP server does not support IDLE');
        }

        $idle_client = $this->client->clone();
        $idle_client->connect();
        $idle_client->openFolder($this->path, true);
        $idle_client->getConnection()->idle();

        $last_action = Carbon::now()->addSeconds($timeout);

        $sequence = ClientManager::get('options.sequence', IMAP::ST_MSGN);

        while (true) {
            // This polymorphic call is fine - Protocol::idle() will throw an exception beforehand
            $line = $idle_client->getConnection()->nextLine(Response::empty());

            if (($pos = strpos($line, 'EXISTS')) !== false) {
                $msgn = (int) substr($line, 2, $pos - 2);

                // Check if the stream is still alive or should be considered stale
                if (! $this->client->isConnected() || $last_action->isBefore(Carbon::now())) {
                    // Reset the connection before interacting with it. Otherwise, the resource might be stale which
                    // would result in a stuck interaction. If you know of a way of detecting a stale resource, please
                    // feel free to improve this logic. I tried a lot but nothing seem to work reliably...
                    // Things that didn't work:
                    //      - Closing the resource with fclose()
                    //      - Verifying the resource with stream_get_meta_data()
                    //      - Bool validating the resource stream (e.g.: (bool)$stream)
                    //      - Sending a NOOP command
                    //      - Sending a null package
                    //      - Reading a null package
                    //      - Catching the fs warning

                    // This polymorphic call is fine - Protocol::idle() will throw an exception beforehand
                    $this->client->getConnection()->reset();
                    // Establish a new connection
                    $this->client->connect();
                }
                $last_action = Carbon::now()->addSeconds($timeout);

                // Always reopen the folder - otherwise the new message number isn't known to the current remote session
                $this->client->openFolder($this->path, true);

                $message = $this->query()->getMessageByMsgn($msgn);
                $message->setSequence($sequence);
                $callback($message);

                $event = $this->getEvent('message', 'new');
                $event::dispatch($message);
            }
        }
    }

    /**
     * Get folder status information from the EXAMINE command.
     */
    public function status(): array
    {
        return $this->client->getConnection()->folderStatus($this->path)->validatedData();
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
        return $this->client->getConnection()->examineFolder($this->path)->validatedData();
    }

    /**
     * Select the current folder.
     */
    public function select(): array
    {
        return $this->client->getConnection()->selectFolder($this->path)->validatedData();
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
    public function setDelimiter($delimiter): void
    {
        if (in_array($delimiter, [null, '', ' ', false]) === true) {
            $delimiter = ClientManager::get('options.delimiter', '/');
        }

        $this->delimiter = $delimiter;
    }
}
