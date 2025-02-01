<?php

namespace Webklex\PHPIMAP;

use finfo;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use Webklex\PHPIMAP\Exceptions\MaskNotFoundException;
use Webklex\PHPIMAP\Support\Masks\AttachmentMask;

/**
 * @property int $part_number
 * @property int $size
 * @property string $content
 * @property string $type
 * @property string $content_type
 * @property string $id
 * @property string $hash
 * @property string $name
 * @property string $description
 * @property string $filename
 * @property ?string $disposition
 * @property string $img_src
 *
 * @method int getPartNumber()
 * @method int setPartNumber(integer $part_number)
 * @method string getContent()
 * @method string setContent(string $content)
 * @method string getType()
 * @method string setType(string $type)
 * @method string getContentType()
 * @method string setContentType(string $content_type)
 * @method string getId()
 * @method string setId(string $id)
 * @method string getHash()
 * @method string setHash(string $hash)
 * @method string getSize()
 * @method string setSize(integer $size)
 * @method string getName()
 * @method string getDisposition()
 * @method string setDisposition(string $disposition)
 * @method string setImgSrc(string $img_src)
 */
class Attachment
{
    use ForwardsCalls;

    /**
     * Message instance.
     */
    protected Message $oMessage;

    /**
     * Part instance.
     */
    protected Part $part;

    /**
     * Used config.
     */
    protected array $config = [];

    /**
     * Attribute holder.
     */
    protected array $attributes = [
        'content' => null,
        'hash' => null,
        'type' => null,
        'part_number' => 0,
        'content_type' => null,
        'id' => null,
        'name' => null,
        'filename' => null,
        'description' => null,
        'disposition' => null,
        'img_src' => null,
        'size' => null,
    ];

    /**
     * Default mask.
     */
    protected string $mask = AttachmentMask::class;

    /**
     * Attachment constructor.
     */
    public function __construct(Message $oMessage, Part $part)
    {
        $this->config = ClientContainer::get('options');

        $this->oMessage = $oMessage;
        $this->part = $part;
        $this->part_number = $part->partNumber;

        if ($this->oMessage->getClient()) {
            $defaultMask = $this->oMessage->getClient()?->getDefaultAttachmentMask();

            if ($defaultMask != null) {
                $this->mask = $defaultMask;
            }
        } else {
            $defaultMask = ClientContainer::getMask('attachment');

            if ($defaultMask != '') {
                $this->mask = $defaultMask;
            }
        }

        $this->findType();
        $this->fetch();
    }

    /**
     * Handle dynamic method calls on the instance.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (str_starts_with($method, 'get')) {
            $name = Str::snake(substr($method, 3));

            if (isset($this->attributes[$name])) {
                return $this->attributes[$name];
            }

            return null;
        }

        if (str_starts_with($method, 'set')) {
            $name = Str::snake(substr($method, 3));

            $this->attributes[$name] = array_pop($arguments);

            return $this->attributes[$name];
        }

        static::throwBadMethodCallException($method);
    }

    /**
     * Handle setting attributes on the instance.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Handle getting attributes on the instance.
     *
     * @return mixed|null
     */
    public function __get(string $name): mixed
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return null;
    }

    /**
     * Determine the structure type.
     */
    protected function findType(): void
    {
        $this->type = match ($this->part->type) {
            Imap::ATTACHMENT_TYPE_MESSAGE => 'message',
            Imap::ATTACHMENT_TYPE_APPLICATION => 'application',
            Imap::ATTACHMENT_TYPE_AUDIO => 'audio',
            Imap::ATTACHMENT_TYPE_IMAGE => 'image',
            Imap::ATTACHMENT_TYPE_VIDEO => 'video',
            Imap::ATTACHMENT_TYPE_MODEL => 'model',
            Imap::ATTACHMENT_TYPE_TEXT => 'text',
            Imap::ATTACHMENT_TYPE_MULTIPART => 'multipart',
            default => 'other',
        };
    }

    /**
     * Fetch the given attachment.
     */
    protected function fetch(): void
    {
        $content = $this->part->content;

        $this->content_type = $this->part->contentType;
        $this->content = $this->oMessage->decodeString($content, $this->part->encoding);

        // Create a hash of the raw part - this can be used to identify the attachment in the message context. However,
        // it is not guaranteed to be unique and collisions are possible.
        // Some additional online resources:
        // - https://en.wikipedia.org/wiki/Hash_collision
        // - https://www.php.net/manual/en/function.hash.php
        // - https://php.watch/articles/php-hash-benchmark
        // Benchmark speeds:
        // -xxh3    ~15.19(GB/s) (requires php-xxhash extension or >= php8.1)
        // -crc32c  ~14.12(GB/s)
        // -sha256  ~0.25(GB/s)
        // xxh3 would be nice to use, because of its extra speed and 32 instead of 8 bytes, but it is not compatible with
        // php < 8.1. crc32c is the next fastest and is compatible with php >= 5.1. sha256 is the slowest, but is compatible
        // with php >= 5.1 and is the most likely to be unique. crc32c is the best compromise between speed and uniqueness.
        // Unique enough for our purposes, but not so slow that it could be a bottleneck.
        $this->hash = hash('crc32c', $this->part->getHeader()->raw."\r\n\r\n".$this->part->content);

        if (($id = $this->part->id) !== null) {
            $this->id = str_replace(['<', '>'], '', $id);
        } else {
            $this->id = $this->hash;
        }

        $this->size = $this->part->bytes;
        $this->disposition = $this->part->disposition;

        if (($filename = $this->part->filename) !== null) {
            $this->filename = $this->decodeName($filename);
        }

        if (($description = $this->part->description) !== null) {
            $this->description = $this->part->getHeader()->decode($description);
        }

        if (($name = $this->part->name) !== null) {
            $this->name = $this->decodeName($name);
        }

        if ($this->part->type == Imap::ATTACHMENT_TYPE_MESSAGE) {
            if ($this->part->ifdescription) {
                if (! $this->name) {
                    $this->name = $this->part->description;
                }
            } elseif (! $this->name) {
                $this->name = $this->part->subtype;
            }
        }

        $this->attributes = array_merge($this->part->getHeader()->getAttributes(), $this->attributes);

        if (! $this->filename) {
            $this->filename = $this->hash;
        }

        if (! $this->name && $this->filename != '') {
            $this->name = $this->filename;
        }
    }

    /**
     * Save the attachment content to your filesystem.
     */
    public function save(string $path, ?string $filename = null): bool
    {
        $filename = $filename ? $this->decodeName($filename) : $this->filename;

        return file_put_contents($path.DIRECTORY_SEPARATOR.$filename, $this->getContent()) !== false;
    }

    /**
     * Decode a given name.
     */
    public function decodeName(?string $name): string
    {
        if ($name !== null) {
            if (str_contains($name, "''")) {
                $parts = explode("''", $name);

                if (EncodingAliases::has($parts[0])) {
                    $name = implode("''", array_slice($parts, 1));
                }
            }

            $decoder = $this->config['decoder']['message'];
            if (preg_match('/=\?([^?]+)\?(Q|B)\?(.+)\?=/i', $name, $matches)) {
                $name = $this->part->getHeader()->decode($name);
            } elseif ($decoder === 'utf-8' && extension_loaded('imap')) {
                $name = imap_utf8($name);
            }

            // check if $name is url encoded
            if (preg_match('/%[0-9A-F]{2}/i', $name)) {
                $name = urldecode($name);
            }

            // sanitize $name
            // order of '..' is important
            $replaces = [
                '/\\\\/' => '',
                '/[\/\0:]+/' => '',
                '/\.+/' => '.',
            ];

            return preg_replace(array_keys($replaces), array_values($replaces), $name);
        }

        return '';
    }

    /**
     * Get the attachment mime type.
     */
    public function getMimeType(): ?string
    {
        return (new finfo)->buffer($this->getContent(), FILEINFO_MIME_TYPE);
    }

    /**
     * Try to guess the attachment file extension.
     */
    public function getExtension(): ?string
    {
        $extension = null;

        $guesser = "\Symfony\Component\Mime\MimeTypes";

        if (class_exists($guesser)) {
            /** @var \Symfony\Component\Mime\MimeTypes $guesser */
            $extensions = $guesser::getDefault()->getExtensions($this->getMimeType());
            $extension = $extensions[0] ?? null;
        }

        if (is_null($extension)) {
            $deprecatedGuesser = "\Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser";
            if (class_exists($deprecatedGuesser)) {
                /** @var \Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser $deprecatedGuesser */
                $extension = $deprecatedGuesser::getInstance()->guess($this->getMimeType());
            }
        }

        if (is_null($extension)) {
            $parts = explode('.', $this->filename);
            $extension = count($parts) > 1 ? end($parts) : null;
        }

        if (is_null($extension)) {
            $parts = explode('.', $this->name);
            $extension = count($parts) > 1 ? end($parts) : null;
        }

        return $extension;
    }

    /**
     * Get all attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the message instance.
     */
    public function getMessage(): Message
    {
        return $this->oMessage;
    }

    /**
     * Set the default mask.
     */
    public function setMask($mask): Attachment
    {
        if (class_exists($mask)) {
            $this->mask = $mask;
        }

        return $this;
    }

    /**
     * Get the used default mask.
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
    public function mask(?string $mask = null): mixed
    {
        $mask = $mask !== null ? $mask : $this->mask;

        if (class_exists($mask)) {
            return new $mask($this);
        }

        throw new MaskNotFoundException('Unknown mask provided: '.$mask);
    }
}
