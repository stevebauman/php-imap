<?php

namespace Webklex\PHPIMAP;

use Webklex\PHPIMAP\Exceptions\InvalidMessageDateException;

class Part
{
    /**
     * Raw part.
     */
    public string $raw = '';

    /**
     * Part type.
     */
    public int $type = IMAP::MESSAGE_TYPE_TEXT;

    /**
     * Part content.
     */
    public string $content = '';

    /**
     * Part subtype.
     */
    public ?string $subtype = null;

    /**
     * Part charset - if available.
     */
    public string $charset = 'utf-8';

    /**
     * Part encoding method.
     */
    public int $encoding = IMAP::MESSAGE_ENC_OTHER;

    /**
     * Alias to check if the part is an attachment.
     */
    public bool $ifdisposition = false;

    /**
     * Indicates if the part is an attachment.
     */
    public ?string $disposition = null;

    /**
     * Alias to check if the part has a description.
     */
    public bool $ifdescription = false;

    /**
     * Part description if available.
     */
    public ?string $description = null;

    /**
     * Part filename if available.
     */
    public ?string $filename = null;

    /**
     * Part name if available.
     */
    public ?string $name = null;

    /**
     * Part id if available.
     */
    public ?string $id = null;

    /**
     * The part number of the current part.
     */
    public int $part_number = 0;

    /**
     * Part length in bytes.
     */
    public int $bytes;

    /**
     * Part content type.
     */
    public ?string $content_type = null;

    /**
     * Part header.
     */
    protected ?Header $header;

    /**
     * Part constructor.
     *
     *
     * @throws InvalidMessageDateException
     */
    public function __construct($raw_part, ?Header $header = null, int $part_number = 0)
    {
        $this->raw = $raw_part;
        $this->header = $header;
        $this->part_number = $part_number;

        $this->parse();
    }

    /**
     * Parse the raw parts.
     *
     * @throws InvalidMessageDateException
     */
    protected function parse(): void
    {
        if ($this->header === null) {
            $body = $this->findHeaders();
        } else {
            $body = $this->raw;
        }

        $this->parseDisposition();
        $this->parseDescription();
        $this->parseEncoding();

        $this->charset = $this->header->get('charset')->first();
        $this->name = $this->header->get('name');
        $this->filename = $this->header->get('filename');

        if ($this->header->get('id')->exist()) {
            $this->id = $this->header->get('id');
        } elseif ($this->header->get('x_attachment_id')->exist()) {
            $this->id = $this->header->get('x_attachment_id');
        } elseif ($this->header->get('content_id')->exist()) {
            $this->id = strtr($this->header->get('content_id'), [
                '<' => '',
                '>' => '',
            ]);
        }

        $content_types = $this->header->get('content_type')->all();

        if (! empty($content_types)) {
            $this->subtype = $this->parseSubtype($content_types);
            $content_type = $content_types[0];
            $parts = explode(';', $content_type);
            $this->content_type = trim($parts[0]);
        }

        $this->content = trim(rtrim($body));
        $this->bytes = strlen($this->content);
    }

    /**
     * Find all available headers and return the leftover body segment.
     */
    protected function findHeaders(): string
    {
        $body = $this->raw;

        while (($pos = strpos($body, "\r\n")) > 0) {
            $body = substr($body, $pos + 2);
        }

        $headers = substr($this->raw, 0, strlen($body) * -1);
        $body = substr($body, 0, -2);

        $this->header = new Header($headers);

        return $body;
    }

    /**
     * Try to parse the subtype if any is present.
     */
    protected function parseSubtype($content_type): ?string
    {
        if (is_array($content_type)) {
            foreach ($content_type as $part) {
                if (strpos($part, '/') !== false) {
                    return $this->parseSubtype($part);
                }
            }

            return null;
        }
        if (($pos = strpos($content_type, '/')) !== false) {
            return substr(explode(';', $content_type)[0], $pos + 1);
        }

        return null;
    }

    /**
     * Try to parse the disposition if any is present.
     */
    protected function parseDisposition(): void
    {
        $content_disposition = $this->header->get('content_disposition')->first();

        if ($content_disposition) {
            $this->ifdisposition = true;
            $this->disposition = (is_array($content_disposition)) ? implode(' ', $content_disposition) : explode(';', $content_disposition)[0];
        }
    }

    /**
     * Try to parse the description if any is present.
     */
    protected function parseDescription(): void
    {
        $content_description = $this->header->get('content_description')->first();

        if ($content_description) {
            $this->ifdescription = true;
            $this->description = $content_description;
        }
    }

    /**
     * Try to parse the encoding if any is present.
     */
    protected function parseEncoding(): void
    {
        $encoding = $this->header->get('content_transfer_encoding')->first();

        if ($encoding) {
            $this->encoding = match (strtolower($encoding)) {
                'quoted-printable' => IMAP::MESSAGE_ENC_QUOTED_PRINTABLE,
                'base64' => IMAP::MESSAGE_ENC_BASE64,
                '7bit' => IMAP::MESSAGE_ENC_7BIT,
                '8bit' => IMAP::MESSAGE_ENC_8BIT,
                'binary' => IMAP::MESSAGE_ENC_BINARY,
                default => IMAP::MESSAGE_ENC_OTHER,
            };
        }
    }

    /**
     * Check if the current part represents an attachment.
     */
    public function isAttachment(): bool
    {
        $valid_disposition = in_array(strtolower($this->disposition ?? ''), ClientManager::get('options.dispositions'));

        if ($this->type == IMAP::MESSAGE_TYPE_TEXT && ($this->ifdisposition == 0 || empty($this->disposition) || ! $valid_disposition)) {
            if (($this->subtype == null || in_array(strtolower($this->subtype), ['plain', 'html'])) && $this->filename == null && $this->name == null) {
                return false;
            }
        }

        if ($this->disposition === 'inline' && $this->filename == null && $this->name == null && ! $this->header->has('content_id')) {
            return false;
        }

        return true;
    }

    /**
     * Get the part header.
     */
    public function getHeader(): ?Header
    {
        return $this->header;
    }
}
