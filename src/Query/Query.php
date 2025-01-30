<?php

namespace Webklex\PHPIMAP\Query;

use Carbon\Carbon;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientContainer;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\GetMessagesFailedException;
use Webklex\PHPIMAP\Exceptions\InvalidMessageDateException;
use Webklex\PHPIMAP\Exceptions\MessageContentFetchingException;
use Webklex\PHPIMAP\Exceptions\MessageFlagException;
use Webklex\PHPIMAP\Exceptions\MessageSearchValidationException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Imap;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Support\MessageCollection;

class Query
{
    protected Collection $query;

    protected string $rawQuery;

    /**
     * The IMAP extensions that should be used.
     *
     * @var string[]
     */
    protected array $extensions;

    protected Client $client;

    protected ?int $limit = null;

    protected int $page = 1;

    protected ?int $fetchOptions = null;

    protected bool $fetchBody = true;

    protected bool $fetchFlags = true;

    /** @var int|string */
    protected mixed $sequence = Imap::NIL;

    protected string $fetchOrder;

    protected string $dateFormat;

    protected bool $softFail = false;

    protected array $errors = [];

    /**
     * Query constructor.
     */
    public function __construct(Client $client, array $extensions = [])
    {
        $this->setClient($client);

        $this->sequence = ClientContainer::get('options.sequence', Imap::ST_MSGN);

        if (ClientContainer::get('options.fetch') === Imap::FT_PEEK) {
            $this->leaveUnread();
        }

        if (ClientContainer::get('options.fetch_order') === 'desc') {
            $this->fetchOrder = 'desc';
        } else {
            $this->fetchOrder = 'asc';
        }

        $this->dateFormat = ClientContainer::get('date_format', 'd M y');
        $this->softFail = ClientContainer::get('options.soft_fail', false);

        $this->setExtensions($extensions);
        $this->query = new Collection;
        $this->boot();
    }

    /**
     * Instance boot method for additional functionality.
     */
    protected function boot(): void {}

    /**
     * Parse a given value.
     */
    protected function parse_value(mixed $value): string
    {
        if ($value instanceof Carbon) {
            $value = $value->format($this->dateFormat);
        }

        return (string) $value;
    }

    /**
     * Check if a given date is a valid carbon object and if not try to convert it.
     */
    protected function parse_date(mixed $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        try {
            $date = Carbon::parse($date);
        } catch (Exception) {
            throw new MessageSearchValidationException;
        }

        return $date;
    }

    /**
     * Get the raw IMAP search query.
     */
    public function compile(): string
    {
        $query = '';

        $this->query->each(function ($statement) use (&$query) {
            if (count($statement) == 1) {
                $query .= $statement[0];
            } else {
                if ($statement[1] === null) {
                    $query .= $statement[0];
                } else {
                    if (is_numeric($statement[1])) {
                        $query .= $statement[0].' '.$statement[1];
                    } else {
                        $query .= $statement[0].' "'.$statement[1].'"';
                    }
                }
            }
            $query .= ' ';
        });

        $this->rawQuery = trim($query);

        return $this->rawQuery;
    }

    /**
     * Perform an imap search request.
     */
    protected function search(): Collection
    {
        $this->compile();

        try {
            $availableMessages = $this->client->getConnection()
                ->search([$this->getRawQuery()], $this->sequence)
                ->getValidatedData();

            return new Collection($availableMessages);
        } catch (RuntimeException|ConnectionFailedException $e) {
            throw new GetMessagesFailedException('failed to fetch messages', 0, $e);
        }
    }

    /**
     * Count all available messages matching the current search criteria.
     */
    public function count(): int
    {
        return $this->search()->count();
    }

    /**
     * Fetch a given id collection.
     */
    protected function fetch(Collection $availableMessages): array
    {
        if ($this->fetchOrder === 'desc') {
            $availableMessages = $availableMessages->reverse();
        }

        $uids = $availableMessages->forPage($this->page, $this->limit)->toArray();

        $extensions = $this->getExtensions();

        if (empty($extensions) === false && method_exists($this->client->getConnection(), 'fetch')) {
            $extensions = $this->client->getConnection()->fetch($extensions, $uids, null, $this->sequence)->getValidatedData();
        }

        $flags = $this->client->getConnection()->flags($uids, $this->sequence)->getValidatedData();

        $headers = $this->client->getConnection()->headers($uids, 'RFC822', $this->sequence)->getValidatedData();

        $contents = [];

        if ($this->getFetchBody()) {
            $contents = $this->client->getConnection()->content($uids, 'RFC822', $this->sequence)->getValidatedData();
        }

        return [
            'uids' => $uids,
            'flags' => $flags,
            'headers' => $headers,
            'contents' => $contents,
            'extensions' => $extensions,
        ];
    }

    /**
     * Make a new message from given raw components.
     */
    protected function make(int $uid, int $msglist, string $header, string $content, array $flags): ?Message
    {
        try {
            return Message::make(
                $uid,
                $msglist,
                $this->getClient(),
                $header,
                $content,
                $flags,
                $this->getFetchOptions(),
                $this->sequence
            );
        } catch (RuntimeException|MessageFlagException|InvalidMessageDateException|MessageContentFetchingException $e) {
            $this->setError($uid, $e);
        }

        $this->handleException($uid);

        return null;
    }

    /**
     * Get the message key for a given message.
     */
    protected function getMessageKey(string $messageKey, int $msglist, Message $message): string
    {
        $key = match ($messageKey) {
            'number' => $message->getMessageNo(),
            'list' => $msglist,
            'uid' => $message->getUid(),
            default => $message->getMessageId(),
        };

        return (string) $key;
    }

    /**
     * Curates a given collection aof messages.
     */
    public function curateMessages(Collection $availableMessages): MessageCollection
    {
        try {
            if ($availableMessages->count() > 0) {
                return $this->populate($availableMessages);
            }

            return MessageCollection::make();
        } catch (Exception $e) {
            throw new GetMessagesFailedException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Populate a given id collection and receive a fully fetched message collection.
     */
    protected function populate(Collection $availableMessages): MessageCollection
    {
        $messages = MessageCollection::make();

        $messages->total($availableMessages->count());

        $messageKey = ClientContainer::get('options.message_key');

        $rawMessages = $this->fetch($availableMessages);

        $msglist = 0;

        foreach ($rawMessages['headers'] as $uid => $header) {
            $content = $rawMessages['contents'][$uid] ?? '';
            $flag = $rawMessages['flags'][$uid] ?? [];
            $extensions = $rawMessages['extensions'][$uid] ?? [];

            $message = $this->make($uid, $msglist, $header, $content, $flag);

            foreach ($extensions as $key => $extension) {
                $message->getHeader()->set($key, $extension);
            }

            if ($message !== null) {
                $key = $this->getMessageKey($messageKey, $msglist, $message);

                $messages->put("$key", $message);
            }

            $msglist++;
        }

        return $messages;
    }

    /**
     * Fetch the current query and return all found messages.
     */
    public function get(): MessageCollection
    {
        return $this->curateMessages($this->search());
    }

    /**
     * Fetch the current query as chunked requests.
     */
    public function chunk(callable $callback, int $chunkSize = 10, int $startChunk = 1): void
    {
        $startChunk = max($startChunk, 1);
        $chunkSize = max($chunkSize, 1);
        $skippedMessagesCount = $chunkSize * ($startChunk - 1);

        $availableMessages = $this->search();
        $availableMessagesCount = max($availableMessages->count() - $skippedMessagesCount, 0);

        if (! $availableMessagesCount) {
            return;
        }

        $previousLimit = $this->limit;
        $previousPage = $this->page;

        $this->limit = $chunkSize;
        $this->page = $startChunk;

        $handledMessagesCount = 0;

        do {
            $messages = $this->populate($availableMessages);

            $handledMessagesCount += $messages->count();

            $callback($messages, $this->page);

            $this->page++;
        } while ($handledMessagesCount < $availableMessagesCount);

        $this->limit = $previousLimit;
        $this->page = $previousPage;
    }

    /**
     * Paginate the current query.
     *
     * @param  int  $perPage  Results you which to receive per page
     * @param  null  $page  The current page you are on (e.g. 0, 1, 2, ...) use `null` to enable auto mode
     * @param  string  $pageName  The page name / uri parameter used for the generated links and the auto mode
     */
    public function paginate(int $perPage = 5, $page = null, string $pageName = 'imap_page'): LengthAwarePaginator
    {
        if ($page === null && isset($_GET[$pageName]) && $_GET[$pageName] > 0) {
            $this->page = intval($_GET[$pageName]);
        } elseif ($page > 0) {
            $this->page = (int) $page;
        }

        $this->limit = $perPage;

        return $this->get()->paginate($perPage, $this->page, $pageName, true);
    }

    /**
     * Get a new Message instance.
     *
     * @param  null  $msglist
     * @param  null  $sequence
     */
    public function getMessage(int $uid, $msglist = null, $sequence = null): Message
    {
        return new Message(
            $uid,
            $msglist,
            $this->getClient(),
            $this->getFetchOptions(),
            $this->getFetchBody(),
            $this->getFetchFlags(),
            $sequence ?: $this->sequence
        );
    }

    /**
     * Get a message by its message number.
     *
     * @param  null  $msglist
     */
    public function getMessageByMsgn($msgn, $msglist = null): Message
    {
        return $this->getMessage($msgn, $msglist, Imap::ST_MSGN);
    }

    /**
     * Get a message by its uid.
     */
    public function getMessageByUid($uid): Message
    {
        return $this->getMessage($uid, null, Imap::ST_UID);
    }

    /**
     * Filter all available uids by a given closure and get a curated list of messages.
     */
    public function filter(callable $closure): MessageCollection
    {
        $connection = $this->getClient()->getConnection();

        $uids = $connection->getUid()->getValidatedData();

        $availableMessages = new Collection;

        if (is_array($uids)) {
            foreach ($uids as $id) {
                if ($closure($id)) {
                    $availableMessages->push($id);
                }
            }
        }

        return $this->curateMessages($availableMessages);
    }

    /**
     * Get all messages with an uid greater or equal to a given UID.
     */
    public function getByUidGreaterOrEqual(int $uid): MessageCollection
    {
        return $this->filter(function ($id) use ($uid) {
            return $id >= $uid;
        });
    }

    /**
     * Get all messages with an uid greater than a given UID.
     */
    public function getByUidGreater(int $uid): MessageCollection
    {
        return $this->filter(function ($id) use ($uid) {
            return $id > $uid;
        });
    }

    /**
     * Get all messages with an uid lower than a given UID.
     */
    public function getByUidLower(int $uid): MessageCollection
    {
        return $this->filter(function ($id) use ($uid) {
            return $id < $uid;
        });
    }

    /**
     * Get all messages with an uid lower or equal to a given UID.
     */
    public function getByUidLowerOrEqual(int $uid): MessageCollection
    {
        return $this->filter(function ($id) use ($uid) {
            return $id <= $uid;
        });
    }

    /**
     * Get all messages with an uid greater than a given UID.
     */
    public function getByUidLowerThan(int $uid): MessageCollection
    {
        return $this->filter(function ($id) use ($uid) {
            return $id < $uid;
        });
    }

    /**
     * Don't mark messages as read when fetching.
     */
    public function leaveUnread(): static
    {
        $this->setFetchOptions(Imap::FT_PEEK);

        return $this;
    }

    /**
     * Mark all messages as read when fetching.
     */
    public function markAsRead(): static
    {
        $this->setFetchOptions(Imap::FT_UID);

        return $this;
    }

    /**
     * Set the sequence type.
     */
    public function setSequence(int $sequence): static
    {
        $this->sequence = $sequence;

        return $this;
    }

    /**
     * Get the sequence type.
     */
    public function getSequence(): int|string
    {
        return $this->sequence;
    }

    /**
     * Get the client instance.
     */
    public function getClient(): Client
    {
        $this->client->checkConnection();

        return $this->client;
    }

    /**
     * Set the limit and page for the current query.
     */
    public function limit(int $limit, int $page = 1): static
    {
        if ($page >= 1) {
            $this->page = $page;
        }
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get the current query collection.
     */
    public function getQuery(): Collection
    {
        return $this->query;
    }

    /**
     * Set all query parameters.
     */
    public function setQuery(array $query): static
    {
        $this->query = new Collection($query);

        return $this;
    }

    /**
     * Get the raw query.
     */
    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }

    /**
     * Set the raw query.
     */
    public function setRawQuery(string $rawQuery): static
    {
        $this->rawQuery = $rawQuery;

        return $this;
    }

    /**
     * Get all applied extensions.
     *
     * @return string[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Set all extensions that should be used.
     *
     * @param  string[]  $extensions
     */
    public function setExtensions(array $extensions): static
    {
        $this->extensions = $extensions;

        if (count($this->extensions) > 0) {
            if (in_array('UID', $this->extensions) === false) {
                $this->extensions[] = 'UID';
            }
        }

        return $this;
    }

    /**
     * Set the client instance.
     */
    public function setClient(Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get the set fetch limit.
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Set the fetch limit.
     */
    public function setLimit(int $limit): static
    {
        $this->limit = $limit <= 0 ? null : $limit;

        return $this;
    }

    /**
     * Get the set page.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Set the page.
     */
    public function setPage(int $page): static
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Set the fetch option flag.
     */
    public function setFetchOptions(int $fetchOptions): static
    {
        $this->fetchOptions = $fetchOptions;

        return $this;
    }

    /**
     * Set the fetch option flag.
     */
    public function fetchOptions(int $fetchOptions): static
    {
        return $this->setFetchOptions($fetchOptions);
    }

    /**
     * Get the fetch option flag.
     */
    public function getFetchOptions(): ?int
    {
        return $this->fetchOptions;
    }

    /**
     * Get the fetch body flag.
     */
    public function getFetchBody(): bool
    {
        return $this->fetchBody;
    }

    /**
     * Set the fetch body flag.
     */
    public function setFetchBody(bool $fetchBody): static
    {
        $this->fetchBody = $fetchBody;

        return $this;
    }

    /**
     * Set the fetch body flag.
     */
    public function fetchBody(bool $fetchBody): static
    {
        return $this->setFetchBody($fetchBody);
    }

    /**
     * Get the fetch body flag.
     */
    public function getFetchFlags(): bool
    {
        return $this->fetchFlags;
    }

    /**
     * Set the fetch flag.
     */
    public function setFetchFlags(bool $fetchFlags): static
    {
        $this->fetchFlags = $fetchFlags;

        return $this;
    }

    /**
     * Set the fetch order.
     */
    public function setFetchOrder(string $fetchOrder): static
    {
        $fetchOrder = strtolower($fetchOrder);

        if (in_array($fetchOrder, ['asc', 'desc'])) {
            $this->fetchOrder = $fetchOrder;
        }

        return $this;
    }

    /**
     * Set the fetch order.
     */
    public function fetchOrder(string $fetchOrder): static
    {
        return $this->setFetchOrder($fetchOrder);
    }

    /**
     * Get the fetch order.
     */
    public function getFetchOrder(): string
    {
        return $this->fetchOrder;
    }

    /**
     * Set the fetch order to 'ascending'.
     */
    public function setFetchOrderAsc(): static
    {
        return $this->setFetchOrder('asc');
    }

    /**
     * Set the fetch order to 'ascending'.
     */
    public function fetchOrderAsc(): static
    {
        return $this->setFetchOrderAsc();
    }

    /**
     * Set the fetch order to 'descending'.
     */
    public function setFetchOrderDesc(): static
    {
        return $this->setFetchOrder('desc');
    }

    /**
     * Set the fetch order to 'descending'.
     */
    public function fetchOrderDesc(): static
    {
        return $this->setFetchOrderDesc();
    }

    /**
     * Set soft fail mode.
     */
    public function softFail(bool $state = true): static
    {
        return $this->setSoftFail($state);
    }

    /**
     * Set soft fail mode.
     */
    public function setSoftFail(bool $state = true): static
    {
        $this->softFail = $state;

        return $this;
    }

    /**
     * Get soft fail mode.
     */
    public function getSoftFail(): bool
    {
        return $this->softFail;
    }

    /**
     * Handle the exception for a given uid.
     */
    protected function handleException(int $uid): void
    {
        if ($this->softFail === false && $this->hasError($uid)) {
            $error = $this->getError($uid);

            throw new GetMessagesFailedException($error->getMessage(), 0, $error);
        }
    }

    /**
     * Add a new error to the error holder.
     */
    protected function setError(int $uid, Exception $error): void
    {
        $this->errors[$uid] = $error;
    }

    /**
     * Check if there are any errors / exceptions present.
     */
    public function hasErrors(?int $uid = null): bool
    {
        if ($uid !== null) {
            return $this->hasError($uid);
        }

        return count($this->errors) > 0;
    }

    /**
     * Check if there is an error / exception present.
     */
    public function hasError(int $uid): bool
    {
        return isset($this->errors[$uid]);
    }

    /**
     * Get all available errors / exceptions.
     */
    public function errors(): array
    {
        return $this->getErrors();
    }

    /**
     * Get all available errors / exceptions.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get a specific error / exception.
     */
    public function error(int $uid): ?Exception
    {
        return $this->getError($uid);
    }

    /**
     * Get a specific error / exception.
     */
    public function getError(int $uid): ?Exception
    {
        if ($this->hasError($uid)) {
            return $this->errors[$uid];
        }

        return null;
    }
}
