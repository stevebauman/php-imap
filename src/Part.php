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
    public int $type = Imap::MESSAGE_TYPE_TEXT;

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
    public int $encoding = Imap::MESSAGE_ENC_OTHER;

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
    public int $partNumber = 0;

    /**
     * Part length in bytes.
     */
    public int $bytes;

    /**
     * Part content type.
     */
    public ?string $contentType = null;

    /**
     * Part header.
     */
    protected ?Header $header;

    /**
     * Part constructor.
     */
    public function __construct(string $rawPart, ?Header $header = null, int $partNumber = 0)
    {
        $this->raw = $rawPart;
        $this->header = $header;
        $this->partNumber = $partNumber;

        $this->parse();
    }

    /**
     * Parse the raw parts.
     *
     * @throws InvalidMessageDateException
     */
    protected function parse(): void
    {
        if (is_null($this->header)) {
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

        $contentTypes = $this->header->get('content_type')->all();

        if (! empty($contentTypes)) {
            $this->subtype = $this->parseSubtype($contentTypes);

            $contentType = $contentTypes[0];

            $parts = explode(';', $contentType);

            $this->contentType = trim($parts[0]);
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
    protected function parseSubtype($contentType): ?string
    {
        if (is_array($contentType)) {
            foreach ($contentType as $part) {
                if (str_contains($part, '/')) {
                    return $this->parseSubtype($part);
                }
            }

            return null;
        }
        if (($pos = strpos($contentType, '/')) !== false) {
            return substr(explode(';', $contentType)[0], $pos + 1);
        }

        return null;
    }

    /**
     * Try to parse the disposition if any is present.
     */
    protected function parseDisposition(): void
    {
        $contentDisposition = $this->header->get('content_disposition')->first();

        if ($contentDisposition) {
            $this->ifdisposition = true;

            $this->disposition = (is_array($contentDisposition))
                ? implode(' ', $contentDisposition)
                : explode(';', $contentDisposition)[0];
        }
    }

    /**
     * Try to parse the description if any is present.
     */
    protected function parseDescription(): void
    {
        $contentDescription = $this->header->get('content_description')->first();

        if ($contentDescription) {
            $this->ifdescription = true;
            $this->description = $contentDescription;
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
                'quoted-printable' => Imap::MESSAGE_ENC_QUOTED_PRINTABLE,
                'base64' => Imap::MESSAGE_ENC_BASE64,
                '7bit' => Imap::MESSAGE_ENC_7BIT,
                '8bit' => Imap::MESSAGE_ENC_8BIT,
                'binary' => Imap::MESSAGE_ENC_BINARY,
                default => Imap::MESSAGE_ENC_OTHER,
            };
        }
    }

    /**
     * Check if the current part represents an attachment.
     */
    public function isAttachment(): bool
    {
        $validDisposition = in_array(strtolower($this->disposition ?? ''), ClientContainer::get('options.dispositions'));

        if ($this->type == Imap::MESSAGE_TYPE_TEXT && ($this->ifdisposition == 0 || empty($this->disposition) || ! $validDisposition)) {
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
