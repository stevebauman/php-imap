<?php

namespace Webklex\PHPIMAP;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use ReflectionClass;
use Webklex\PHPIMAP\Exceptions\MaskNotFoundException;
use Webklex\PHPIMAP\Exceptions\MessageContentFetchingException;
use Webklex\PHPIMAP\Exceptions\MessageFlagException;
use Webklex\PHPIMAP\Exceptions\MessageHeaderFetchingException;
use Webklex\PHPIMAP\Exceptions\MessageSizeFetchingException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Support\AttachmentCollection;
use Webklex\PHPIMAP\Support\FlagCollection;
use Webklex\PHPIMAP\Support\Masks\MessageMask;
use Webklex\PHPIMAP\Support\MessageCollection;
use Webklex\PHPIMAP\Traits\HasEvents;

/**
 * @property int $msglist
 * @property int $uid
 * @property int $msgn
 * @property int $size
 * @property Attribute $subject
 * @property Attribute $message_id
 * @property Attribute $message_no
 * @property Attribute $references
 * @property Attribute $date
 * @property Attribute $from
 * @property Attribute $to
 * @property Attribute $cc
 * @property Attribute $bcc
 * @property Attribute $reply_to
 * @property Attribute $in_reply_to
 * @property Attribute $sender
 *
 * @method int getMsglist()
 * @method int setMsglist($msglist)
 * @method int getUid()
 * @method int getMsgn()
 * @method int getSize()
 * @method Attribute getPriority()
 * @method Attribute getSubject()
 * @method Attribute getMessageId()
 * @method Attribute getMessageNo()
 * @method Attribute getReferences()
 * @method Attribute getDate()
 * @method Attribute getFrom()
 * @method Attribute getTo()
 * @method Attribute getCc()
 * @method Attribute getBcc()
 * @method Attribute getReplyTo()
 * @method Attribute getInReplyTo()
 * @method Attribute getSender()
 */
class Message
{
    use ForwardsCalls;
    use HasEvents;

    /**
     * Client instance.
     */
    protected ?Client $client = null;

    /**
     * Default mask.
     */
    protected string $mask = MessageMask::class;

    /**
     * Used config.
     */
    protected array $config = [];

    /**
     * Attribute holder.
     *
     * @var Attribute[]|array
     */
    protected array $attributes = [];

    /**
     * The message folder path.
     */
    protected string $folderPath;

    /**
     * Fetch body options.
     */
    public ?int $fetchOptions = null;

    /**
     * Sequence type.
     */
    protected int $sequence = IMAP::NIL;

    /**
     * Fetch body options.
     */
    public bool $fetchBody = true;

    /**
     * Fetch flags options.
     */
    public bool $fetchFlags = true;

    /**
     * Message header.
     */
    public ?Header $header = null;

    /**
     * Raw message body.
     */
    protected string $rawBody = '';

    /**
     * Message structure.
     */
    protected ?Structure $structure = null;

    /**
     * Message body components.
     */
    public array $bodies = [];

    /**
     * Message attachments.
     */
    public AttachmentCollection $attachments;

    /**
     * Message flags.
     */
    public FlagCollection $flags;

    /**
     * A list of all available and supported flags.
     */
    protected ?array $availableFlags = null;

    /**
     * Message constructor.
     */
    public function __construct(int $uid, ?int $msglist, Client $client, ?int $fetchOptions = null, bool $fetchBody = false, bool $fetchFlags = false, ?int $sequence = null)
    {
        $this->boot();

        $defaultMask = $client->getDefaultMessageMask();

        if ($defaultMask != null) {
            $this->mask = $defaultMask;
        }

        $this->events['message'] = $client->getDefaultEvents('message');
        $this->events['flag'] = $client->getDefaultEvents('flag');

        $this->folderPath = $client->getFolderPath();

        $this->setSequence($sequence);
        $this->setFetchOption($fetchOptions);
        $this->setFetchBodyOption($fetchBody);
        $this->setFetchFlagsOption($fetchFlags);

        $this->client = $client;
        $this->client->openFolder($this->folderPath);

        $this->setSequenceId($uid, $msglist);

        if ($this->fetchOptions == IMAP::FT_PEEK) {
            $this->parseFlags();
        }

        $this->parseHeader();

        if ($this->getFetchBodyOption() === true) {
            $this->parseBody();
        }

        if ($this->getFetchFlagsOption() === true && $this->fetchOptions !== IMAP::FT_PEEK) {
            $this->parseFlags();
        }
    }

    /**
     * Create a new instance without fetching the message header and providing them raw instead.
     */
    public static function make(int $uid, ?int $msglist, Client $client, string $rawHeader, string $rawBody, array $rawFlags, ?int $fetchOptions = null, ?int $sequence = null): Message
    {
        $reflection = new ReflectionClass(self::class);

        /** @var Message $instance */
        $instance = $reflection->newInstanceWithoutConstructor();

        $instance->boot();

        if ($defaultMask = $client->getDefaultMessageMask()) {
            $instance->setMask($defaultMask);
        }

        $instance->setEvents([
            'message' => $client->getDefaultEvents('message'),
            'flag' => $client->getDefaultEvents('flag'),
        ]);

        $instance->setFolderPath($client->getFolderPath());
        $instance->setSequence($sequence);
        $instance->setFetchOption($fetchOptions);

        $instance->setClient($client);
        $instance->setSequenceId($uid, $msglist);

        $instance->parseRawHeader($rawHeader);
        $instance->parseRawFlags($rawFlags);
        $instance->parseRawBody($rawBody);
        $instance->peek();

        return $instance;
    }

    /**
     * Create a new message instance by reading and loading a file or remote location.
     */
    public static function fromFile(string $filename): Message
    {
        $blob = file_get_contents($filename);

        if ($blob === false) {
            throw new RuntimeException('Unable to read file');
        }

        return self::fromString($blob);
    }

    /**
     * Create a new message instance by reading and loading a string.
     */
    public static function fromString(string $blob): Message
    {
        $reflection = new ReflectionClass(self::class);

        /** @var Message $instance */
        $instance = $reflection->newInstanceWithoutConstructor();

        $instance->boot();

        if ($defaultMask = ClientManager::getMask('message')) {
            $instance->setMask($defaultMask);
        } else {
            throw new MaskNotFoundException('Unknown message mask provided');
        }

        if (! str_contains($blob, "\r\n")) {
            $blob = str_replace("\n", "\r\n", $blob);
        }

        $rawHeader = substr($blob, 0, strpos($blob, "\r\n\r\n"));
        $rawBody = substr($blob, strlen($rawHeader) + 4);

        $instance->parseRawHeader($rawHeader);
        $instance->parseRawBody($rawBody);

        $instance->setUid(0);

        return $instance;
    }

    /**
     * Boot a new instance.
     */
    public function boot(): void
    {
        $this->attributes = [];

        $this->config = ClientManager::get('options');
        $this->availableFlags = ClientManager::get('flags');

        $this->attachments = AttachmentCollection::make();
        $this->flags = FlagCollection::make();
    }

    /**
     * Call dynamic attribute setter and getter methods.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (strtolower(substr($method, 0, 3)) === 'get') {
            $name = Str::snake(substr($method, 3));

            return $this->get($name);
        } elseif (strtolower(substr($method, 0, 3)) === 'set') {
            $name = Str::snake(substr($method, 3));

            if (in_array($name, array_keys($this->attributes))) {
                $this->__set($name, array_pop($arguments));

                return null;
            }
        }

        static::throwBadMethodCallException($method);
    }

    /**
     * Magic setter.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Magic getter.
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Get an available message or message header attribute.
     *
     * @return Attribute|mixed
     */
    public function get(string $name): mixed
    {
        if (isset($this->attributes[$name]) && $this->attributes[$name] !== null) {
            return $this->attributes[$name];
        }

        switch ($name) {
            case 'uid':
                $this->attributes[$name] = $this->client->getConnection()
                    ->getUid($this->msgn)
                    ->validate()
                    ->integer();

                return $this->attributes[$name];
            case 'msgn':
                $this->attributes[$name] = $this->client->getConnection()
                    ->getMessageNumber($this->uid)
                    ->validate()
                    ->integer();

                return $this->attributes[$name];
            case 'size':
                if (! isset($this->attributes[$name])) {
                    $this->fetchSize();
                }

                return $this->attributes[$name];
        }

        return $this->header->get($name);
    }

    /**
     * Check if the Message has a text body.
     */
    public function hasTextBody(): bool
    {
        return isset($this->bodies['text']) && $this->bodies['text'] !== '';
    }

    /**
     * Get the Message text body.
     */
    public function getTextBody(): string
    {
        if (! isset($this->bodies['text'])) {
            return '';
        }

        return $this->bodies['text'];
    }

    /**
     * Check if the Message has a html body.
     */
    public function hasHTMLBody(): bool
    {
        return isset($this->bodies['html']) && $this->bodies['html'] !== '';
    }

    /**
     * Get the Message html body.
     */
    public function getHTMLBody(): string
    {
        if (! isset($this->bodies['html'])) {
            return '';
        }

        return $this->bodies['html'];
    }

    /**
     * Parse all defined headers.
     */
    protected function parseHeader(): void
    {
        $sequenceId = $this->getSequenceId();

        $headers = $this->client->getConnection()
            ->headers([$sequenceId], 'RFC822', $this->sequence)
            ->getValidatedData();

        if (! isset($headers[$sequenceId])) {
            throw new MessageHeaderFetchingException('no headers found', 0);
        }

        $this->parseRawHeader($headers[$sequenceId]);
    }

    /**
     * Parse and set the header of the message.
     */
    public function parseRawHeader(string $rawHeader): void
    {
        $this->header = new Header($rawHeader);
    }

    /**
     * Parse and set the flags of the message.
     */
    public function parseRawFlags(array $rawFlags): void
    {
        $this->flags = FlagCollection::make();

        foreach ($rawFlags as $flag) {
            if (str_starts_with($flag, '\\')) {
                $flag = substr($flag, 1);
            }

            $flagKey = strtolower($flag);

            if ($this->availableFlags === null || in_array($flagKey, $this->availableFlags)) {
                $this->flags->put($flagKey, $flag);
            }
        }
    }

    /**
     * Parse additional flags.
     */
    protected function parseFlags(): void
    {
        $this->client->openFolder($this->folderPath);
        $this->flags = FlagCollection::make();

        $sequenceId = $this->getSequenceId();

        try {
            $flags = $this->client->getConnection()
                ->flags([$sequenceId], $this->sequence)
                ->getValidatedData();
        } catch (Exceptions\RuntimeException $e) {
            throw new MessageFlagException('flag could not be fetched', 0, $e);
        }

        if (isset($flags[$sequenceId])) {
            $this->parseRawFlags($flags[$sequenceId]);
        }
    }

    /**
     * Parse the Message body.
     */
    public function parseBody(): Message
    {
        $this->client->openFolder($this->folderPath);

        $sequenceId = $this->getSequenceId();

        try {
            $contents = $this->client->getConnection()->content(
                [$sequenceId],
                'RFC822',
                $this->sequence
            )->getValidatedData();
        } catch (Exceptions\RuntimeException $e) {
            throw new MessageContentFetchingException('failed to fetch content', 0, previous: $e);
        }

        if (! isset($contents[$sequenceId])) {
            throw new MessageContentFetchingException('no content found', 0);
        }

        $content = $contents[$sequenceId];

        $body = $this->parseRawBody($content);

        $this->peek();

        return $body;
    }

    /**
     * Fetches the size for this message.
     */
    protected function fetchSize(): void
    {
        $sequenceId = $this->getSequenceId();

        $sizes = $this->client->getConnection()
            ->sizes([$sequenceId], $this->sequence)
            ->getValidatedData();

        if (! isset($sizes[$sequenceId])) {
            throw new MessageSizeFetchingException('sizes did not set an array entry for the supplied sequence_id', 0);
        }
        $this->attributes['size'] = $sizes[$sequenceId];
    }

    /**
     * Handle auto "Seen" flag handling.
     */
    public function peek(): void
    {
        if ($this->fetchOptions == IMAP::FT_PEEK) {
            if ($this->getFlags()->get('seen') == null) {
                $this->unsetFlag('Seen');
            }
        } elseif ($this->getFlags()->get('seen') == null) {
            $this->setFlag('Seen');
        }
    }

    /**
     * Parse a given message body.
     */
    public function parseRawBody(string $rawBody): Message
    {
        $this->structure = new Structure($rawBody, $this->header);

        $this->fetchStructure($this->structure);

        return $this;
    }

    /**
     * Fetch the Message structure.
     */
    protected function fetchStructure(Structure $structure): void
    {
        $this->client?->openFolder($this->folderPath);

        foreach ($structure->parts as $part) {
            $this->fetchPart($part);
        }
    }

    /**
     * Fetch a given part.
     */
    protected function fetchPart(Part $part): void
    {
        if ($part->isAttachment()) {
            $this->fetchAttachment($part);
        } else {
            $encoding = $this->getEncoding($part);

            $content = $this->decodeString($part->content, $part->encoding);

            // We don't need to do convertEncoding() if charset is ASCII (us-ascii):
            //     ASCII is a subset of UTF-8, so all ASCII files are already UTF-8 encoded
            //     https://stackoverflow.com/a/11303410
            //
            // us-ascii is the same as ASCII:
            //     ASCII is the traditional name for the encoding system; the Internet Assigned Numbers Authority (IANA)
            //     prefers the updated name US-ASCII, which clarifies that this system was developed in the US and
            //     based on the typographical symbols predominantly in use there.
            //     https://en.wikipedia.org/wiki/ASCII
            //
            // convertEncoding() function basically means convertToUtf8(), so when we convert ASCII string into UTF-8 it gets broken.
            if ($encoding != 'us-ascii') {
                $content = $this->convertEncoding($content, $encoding);
            }

            $this->addBody($part->subtype ?? '', $content);
        }
    }

    /**
     * Add a body to the message.
     */
    protected function addBody(string $subtype, string $content): void
    {
        $subtype = strtolower($subtype);
        $subtype = $subtype == 'plain' || $subtype == '' ? 'text' : $subtype;

        if (isset($this->bodies[$subtype]) && $this->bodies[$subtype] !== null && $this->bodies[$subtype] !== '') {
            if ($content !== '') {
                $this->bodies[$subtype] .= "\n".$content;
            }
        } else {
            $this->bodies[$subtype] = $content;
        }
    }

    /**
     * Fetch the Message attachment.
     */
    protected function fetchAttachment(Part $part): void
    {
        $oAttachment = new Attachment($this, $part);

        if ($oAttachment->getSize() > 0) {
            if ($oAttachment->getId() !== null && $this->attachments->offsetExists($oAttachment->getId())) {
                $this->attachments->put($oAttachment->getId(), $oAttachment);
            } else {
                $this->attachments->push($oAttachment);
            }
        }
    }

    /**
     * Fail proof setter for $fetch_option.
     */
    public function setFetchOption(?int $option): Message
    {
        if (is_int($option) === true) {
            $this->fetchOptions = $option;
        } elseif (is_null($option) === true) {
            $config = ClientManager::get('options.fetch', IMAP::FT_UID);

            $this->fetchOptions = is_int($config) ? $config : 1;
        }

        return $this;
    }

    /**
     * Set the sequence type.
     */
    public function setSequence(?int $sequence): Message
    {
        if (is_int($sequence)) {
            $this->sequence = $sequence;
        } elseif (is_null($sequence)) {
            $config = ClientManager::get('options.sequence', IMAP::ST_MSGN);

            $this->sequence = is_int($config) ? $config : IMAP::ST_MSGN;
        }

        return $this;
    }

    /**
     * Fail proof setter for $fetch_body.
     */
    public function setFetchBodyOption($option): Message
    {
        if (is_bool($option)) {
            $this->fetchBody = $option;
        } elseif (is_null($option)) {
            $config = ClientManager::get('options.fetch_body', true);

            $this->fetchBody = is_bool($config) ? $config : true;
        }

        return $this;
    }

    /**
     * Fail proof setter for $fetch_flags.
     */
    public function setFetchFlagsOption($option): Message
    {
        if (is_bool($option)) {
            $this->fetchFlags = $option;
        } elseif (is_null($option)) {
            $config = ClientManager::get('options.fetch_flags', true);

            $this->fetchFlags = is_bool($config) ? $config : true;
        }

        return $this;
    }

    /**
     * Decode a given string.
     */
    public function decodeString($string, $encoding): string
    {
        switch ($encoding) {
            case IMAP::MESSAGE_ENC_BINARY:
                if (extension_loaded('imap')) {
                    return base64_decode(imap_binary($string));
                }

                return base64_decode($string);
            case IMAP::MESSAGE_ENC_BASE64:
                return base64_decode($string);
            case IMAP::MESSAGE_ENC_QUOTED_PRINTABLE:
                return quoted_printable_decode($string);
            case IMAP::MESSAGE_ENC_8BIT:
            case IMAP::MESSAGE_ENC_7BIT:
            case IMAP::MESSAGE_ENC_OTHER:
            default:
                return $string;
        }
    }

    /**
     * Convert the encoding.
     *
     * @return mixed|string
     */
    public function convertEncoding($str, string $from = 'ISO-8859-2', string $to = 'UTF-8'): mixed
    {
        $from = EncodingAliases::get($from);
        $to = EncodingAliases::get($to);

        if ($from === $to) {
            return $str;
        }

        // We don't need to do convertEncoding() if charset is ASCII (us-ascii):
        //     ASCII is a subset of UTF-8, so all ASCII files are already UTF-8 encoded
        //     https://stackoverflow.com/a/11303410
        //
        // us-ascii is the same as ASCII:
        //     ASCII is the traditional name for the encoding system; the Internet Assigned Numbers Authority (IANA)
        //     prefers the updated name US-ASCII, which clarifies that this system was developed in the US and
        //     based on the typographical symbols predominantly in use there.
        //     https://en.wikipedia.org/wiki/ASCII
        //
        // convertEncoding() function basically means convertToUtf8(), so when we convert ASCII string into UTF-8 it gets broken.
        if (strtolower($from ?? '') == 'us-ascii' && $to == 'UTF-8') {
            return $str;
        }

        if (function_exists('iconv') && ! EncodingAliases::isUtf7($from) && ! EncodingAliases::isUtf7($to)) {
            try {
                return iconv($from, $to.'//IGNORE', $str);
            } catch (Exception) {
                return @iconv($from, $to, $str);
            }
        } else {
            if (! $from) {
                return mb_convert_encoding($str, $to);
            }

            return mb_convert_encoding($str, $to, $from);
        }
    }

    /**
     * Get the encoding of a given abject.
     */
    public function getEncoding(object|string $structure): string
    {
        if (property_exists($structure, 'parameters')) {
            foreach ($structure->parameters as $parameter) {
                if (strtolower($parameter->attribute) == 'charset') {
                    return EncodingAliases::get($parameter->value, 'ISO-8859-2');
                }
            }
        } elseif (property_exists($structure, 'charset')) {
            return EncodingAliases::get($structure->charset, 'ISO-8859-2');
        } elseif (is_string($structure) === true) {
            return EncodingAliases::detectEncoding($structure);
        }

        return 'UTF-8';
    }

    /**
     * Get the messages folder.
     */
    public function getFolder(): ?Folder
    {
        return $this->client->getFolderByPath($this->folderPath);
    }

    /**
     * Create a message thread based on the current message.
     */
    public function thread(?Folder $sentFolder = null, ?MessageCollection &$thread = null, ?Folder $folder = null): MessageCollection
    {
        $thread = $thread ?: MessageCollection::make();

        $folder = $folder ?: $this->getFolder();

        $sentFolder = $sentFolder ?: $this->client->getFolderByPath(
            ClientManager::get('options.common_folders.sent', 'INBOX/Sent')
        );

        /** @var Message $message */
        foreach ($thread as $message) {
            if ($message->message_id->first() == $this->message_id->first()) {
                return $thread;
            }
        }

        $thread->push($this);

        $this->fetchThreadByInReplyTo($thread, $this->message_id, $folder, $folder, $sentFolder);
        $this->fetchThreadByInReplyTo($thread, $this->message_id, $sentFolder, $folder, $sentFolder);

        foreach ($this->in_reply_to->all() as $inReplyTo) {
            $this->fetchThreadByMessageId($thread, $inReplyTo, $folder, $folder, $sentFolder);
            $this->fetchThreadByMessageId($thread, $inReplyTo, $sentFolder, $folder, $sentFolder);
        }

        return $thread;
    }

    /**
     * Fetch a partial thread by message id.
     */
    protected function fetchThreadByInReplyTo(MessageCollection &$thread, string $inReplyTo, Folder $primaryFolder, Folder $secondaryFolder, Folder $sentFolder): void
    {
        $primaryFolder->query()
            ->inReplyTo($inReplyTo)
            ->setFetchBody($this->getFetchBodyOption())
            ->leaveUnread()->get()->each(function ($message) use (&$thread, $secondaryFolder, $sentFolder) {
                /** @var Message $message */
                $message->thread($sentFolder, $thread, $secondaryFolder);
            });
    }

    /**
     * Fetch a partial thread by message id.
     */
    protected function fetchThreadByMessageId(MessageCollection &$thread, string $messageId, Folder $primaryFolder, Folder $secondaryFolder, Folder $sentFolder): void
    {
        $primaryFolder->query()
            ->messageId($messageId)
            ->setFetchBody($this->getFetchBodyOption())
            ->leaveUnread()->get()->each(function ($message) use (&$thread, $secondaryFolder, $sentFolder) {
                /** @var Message $message */
                $message->thread($sentFolder, $thread, $secondaryFolder);
            });
    }

    /**
     * Copy the current Messages to a mailbox.
     */
    public function copy(string $folderPath, bool $expunge = false): ?Message
    {
        $this->client->openFolder($folderPath);

        $status = $this->client->getConnection()
            ->examineFolder($folderPath)
            ->getValidatedData();

        if (isset($status['uidnext'])) {
            $nextUid = $status['uidnext'];

            if ((int) $nextUid <= 0) {
                return null;
            }

            /** @var Folder $folder */
            $folder = $this->client->getFolderByPath($folderPath);

            $this->client->openFolder($this->folderPath);

            if ($this->client->getConnection()->copyMessage($folder->path, $this->getSequenceId(), null, $this->sequence)->getValidatedData()) {
                return $this->fetchNewMail($folder, $nextUid, 'copied', $expunge);
            }
        }

        return null;
    }

    /**
     * Move the current Messages to a mailbox.
     */
    public function move(string $folderPath, bool $expunge = false): ?Message
    {
        $this->client->openFolder($folderPath);

        $status = $this->client->getConnection()
            ->examineFolder($folderPath)
            ->getValidatedData();

        if (isset($status['uidnext'])) {
            $nextUid = $status['uidnext'];

            if ((int) $nextUid <= 0) {
                return null;
            }

            /** @var Folder $folder */
            $folder = $this->client->getFolderByPath($folderPath);

            $this->client->openFolder($this->folderPath);

            if ($this->client->getConnection()->moveMessage($folder->path, $this->getSequenceId(), null, $this->sequence)->getValidatedData()) {
                return $this->fetchNewMail($folder, $nextUid, 'moved', $expunge);
            }
        }

        return null;
    }

    /**
     * Fetch a new message and fire a given event.
     */
    protected function fetchNewMail(Folder $folder, int $nextUid, string $event, bool $expunge): Message
    {
        if ($expunge) {
            $this->client->expunge();
        }

        $this->client->openFolder($folder->path);

        if ($this->sequence === IMAP::ST_UID) {
            $sequenceId = $nextUid;
        } else {
            $sequenceId = $this->client->getConnection()
                ->getMessageNumber($nextUid)
                ->getValidatedData();
        }

        $message = $folder->query()->getMessage($sequenceId, null, $this->sequence);

        $this->dispatch('message', $event, $this, $message);

        return $message;
    }

    /**
     * Delete the current Message.
     */
    public function delete(bool $expunge = true, ?string $trashPath = null, bool $forceMove = false): bool
    {
        $status = $this->setFlag('Deleted');

        if ($forceMove) {
            $this->move(
                $trashPath ?? $this->config['common_folders']['trash']
            );
        }

        if ($expunge) {
            $this->client->expunge();
        }

        $this->dispatch('message', 'deleted', $this);

        return $status;
    }

    /**
     * Restore a deleted Message.
     */
    public function restore(bool $expunge = true): bool
    {
        $status = $this->unsetFlag('Deleted');

        if ($expunge) {
            $this->client->expunge();
        }

        $this->dispatch('message', 'restored', $this);

        return $status;
    }

    /**
     * Set a given flag.
     */
    public function setFlag(array|string $flag): bool
    {
        $this->client->openFolder($this->folderPath);
        $flag = '\\'.trim(is_array($flag) ? implode(' \\', $flag) : $flag);
        $sequenceId = $this->getSequenceId();

        try {
            $status = $this->client->getConnection()
                ->store([$flag], $sequenceId, $sequenceId, '+', true, $this->sequence)
                ->getValidatedData();
        } catch (Exceptions\RuntimeException $e) {
            throw new MessageFlagException('flag could not be set', 0, $e);
        }

        $this->parseFlags();

        $this->dispatch('flag', 'new', $this, $flag);

        return (bool) $status;
    }

    /**
     * Unset a given flag.
     */
    public function unsetFlag(array|string $flag): bool
    {
        $this->client->openFolder($this->folderPath);

        $flag = '\\'.trim(is_array($flag) ? implode(' \\', $flag) : $flag);
        $sequenceId = $this->getSequenceId();

        try {
            $status = $this->client->getConnection()
                ->store([$flag], $sequenceId, $sequenceId, '-', true, $this->sequence)
                ->getValidatedData();
        } catch (Exceptions\RuntimeException $e) {
            throw new MessageFlagException('flag could not be removed', 0, $e);
        }

        $this->parseFlags();

        $this->dispatch('flag', 'deleted', $this, $flag);

        return (bool) $status;
    }

    /**
     * Set a given flag.
     */
    public function addFlag(array|string $flag): bool
    {
        return $this->setFlag($flag);
    }

    /**
     * Unset a given flag.
     */
    public function removeFlag(array|string $flag): bool
    {
        return $this->unsetFlag($flag);
    }

    /**
     * Get all message attachments.
     */
    public function getAttachments(): AttachmentCollection
    {
        return $this->attachments;
    }

    /**
     * Get all message attachments.
     */
    public function attachments(): AttachmentCollection
    {
        return $this->getAttachments();
    }

    /**
     * Checks if there are any attachments present.
     */
    public function hasAttachments(): bool
    {
        return $this->attachments->isNotEmpty();
    }

    /**
     * Get the raw body.
     */
    public function getRawBody(): string
    {
        if (empty($this->rawBody)) {
            $this->rawBody = $this->structure->raw;
        }

        return $this->rawBody;
    }

    /**
     * Get the message header.
     */
    public function getHeader(): ?Header
    {
        return $this->header;
    }

    /**
     * Get the current client.
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * Get the used fetch option.
     */
    public function getFetchOptions(): ?int
    {
        return $this->fetchOptions;
    }

    /**
     * Get the used fetch body option.
     */
    public function getFetchBodyOption(): bool
    {
        return $this->fetchBody;
    }

    /**
     * Get the used fetch flags option.
     */
    public function getFetchFlagsOption(): bool
    {
        return $this->fetchFlags;
    }

    /**
     * Get all available bodies.
     */
    public function getBodies(): array
    {
        return $this->bodies;
    }

    /**
     * Get all set flags.
     */
    public function getFlags(): FlagCollection
    {
        return $this->flags;
    }

    /**
     * Get all set flags.
     */
    public function flags(): FlagCollection
    {
        return $this->getFlags();
    }

    /**
     * Check if a flag is set.
     */
    public function hasFlag(string $flag): bool
    {
        $flag = str_replace('\\', '', strtolower($flag));

        return $this->getFlags()->has($flag);
    }

    /**
     * Get the fetched structure.
     */
    public function getStructure(): ?Structure
    {
        return $this->structure;
    }

    /**
     * Check if a message matches another by comparing basic attributes.
     */
    public function is(?Message $message = null): bool
    {
        if (is_null($message)) {
            return false;
        }

        return $this->uid == $message->uid
            && $this->message_id->first() == $message->message_id->first()
            && $this->subject->first() == $message->subject->first()
            && $this->date->toDate()->eq($message->date->toDate());
    }

    /**
     * Get all message attributes.
     */
    public function getAttributes(): array
    {
        return array_merge($this->attributes, $this->header->getAttributes());
    }

    /**
     * Set the message mask.
     */
    public function setMask($mask): Message
    {
        if (class_exists($mask)) {
            $this->mask = $mask;
        }

        return $this;
    }

    /**
     * Get the used message mask.
     */
    public function getMask(): string
    {
        return $this->mask;
    }

    /**
     * Get a masked instance by providing a mask name.
     *
     * @throws MaskNotFoundException
     */
    public function mask(mixed $mask = null): mixed
    {
        $mask = $mask !== null ? $mask : $this->mask;

        if (class_exists($mask)) {
            return new $mask($this);
        }

        throw new MaskNotFoundException('Unknown mask provided: '.$mask);
    }

    /**
     * Get the message path aka folder path.
     */
    public function getFolderPath(): string
    {
        return $this->folderPath;
    }

    /**
     * Set the message path aka folder path.
     */
    public function setFolderPath($folderPath): Message
    {
        $this->folderPath = $folderPath;

        return $this;
    }

    /**
     * Set the config.
     */
    public function setConfig(array $config): Message
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the config.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set the available flags.
     */
    public function setAvailableFlags($availableFlags): Message
    {
        $this->availableFlags = $availableFlags;

        return $this;
    }

    /**
     * Get the available flags.
     */
    public function getAvailableFlags(): array
    {
        return $this->availableFlags;
    }

    /**
     * Set the attachment collection.
     */
    public function setAttachments($attachments): Message
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * Set the flag collection.
     */
    public function setFlags($flags): Message
    {
        $this->flags = $flags;

        return $this;
    }

    /**
     * Set the client.
     */
    public function setClient($client): Message
    {
        $this->client = $client;
        $this->client?->openFolder($this->folderPath);

        return $this;
    }

    /**
     * Set the message number.
     */
    public function setUid(int $uid): Message
    {
        $this->uid = $uid;
        $this->msgn = null;
        $this->msglist = null;

        return $this;
    }

    /**
     * Set the message number.
     */
    public function setMsgn(int $msgn, ?int $msglist = null): Message
    {
        $this->msgn = $msgn;
        $this->msglist = $msglist;
        $this->uid = null;

        return $this;
    }

    /**
     * Get the current sequence type.
     */
    public function getSequence(): int
    {
        return $this->sequence;
    }

    /**
     * Get the current sequence id (either a UID or a message number!).
     */
    public function getSequenceId(): int
    {
        return $this->sequence === IMAP::ST_UID ? $this->uid : $this->msgn;
    }

    /**
     * Set the sequence id.
     */
    public function setSequenceId($uid, ?int $msglist = null): void
    {
        if ($this->getSequence() === IMAP::ST_UID) {
            $this->setUid($uid);
            $this->setMsglist($msglist);
        } else {
            $this->setMsgn($uid, $msglist);
        }
    }

    /**
     * Safe the entire message in a file.
     */
    public function save($filename): bool|int
    {
        return file_put_contents($filename, $this->header->raw."\r\n\r\n".$this->structure->raw);
    }
}
