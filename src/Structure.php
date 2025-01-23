<?php

namespace Webklex\PHPIMAP;

use Webklex\PHPIMAP\Exceptions\MessageContentFetchingException;

class Structure
{
    /**
     * Raw structure.
     */
    public string $raw = '';

    /**
     * Header instance.
     */
    protected Header $header;

    /**
     * Message type (if multipart or not).
     */
    public int $type = IMAP::MESSAGE_TYPE_TEXT;

    /**
     * All available parts.
     *
     * @var Part[]
     */
    public array $parts = [];

    /**
     * Config holder.
     */
    protected array $config = [];

    /**
     * Structure constructor.
     */
    public function __construct(string $rawStructure, Header $header)
    {
        $this->raw = $rawStructure;
        $this->header = $header;
        $this->config = ClientManager::get('options');
        $this->parse();
    }

    /**
     * Parse the given raw structure.
     */
    protected function parse(): void
    {
        $this->findContentType();

        $this->parts = $this->findParts();
    }

    /**
     * Determine the message content type.
     */
    public function findContentType(): void
    {
        $contentType = $this->header->get('content_type')->first();

        if ($contentType && stripos($contentType, 'multipart') === 0) {
            $this->type = IMAP::MESSAGE_TYPE_MULTIPART;
        } else {
            $this->type = IMAP::MESSAGE_TYPE_TEXT;
        }
    }

    /**
     * Find all available headers and return the leftover body segment.
     *
     * @return Part[]
     */
    protected function parsePart(string $context, int $partNumber = 0): array
    {
        $body = $context;

        while (($pos = strpos($body, "\r\n")) > 0) {
            $body = substr($body, $pos + 2);
        }

        $headers = substr($context, 0, strlen($body) * -1);
        $body = substr($body, 0, -2);

        $headers = new Header($headers);

        if (($boundary = $headers->getBoundary()) !== null) {
            return $this->detectParts($boundary, $body, $partNumber);
        }

        return [new Part($body, $headers, $partNumber)];
    }

    /**
     * Detect all available parts.
     */
    protected function detectParts(string $boundary, string $context, int $partNumber = 0): array
    {
        $baseParts = explode($boundary, $context);

        $finalParts = [];

        foreach ($baseParts as $ctx) {
            $ctx = substr($ctx, 2);

            if ($ctx !== '--' && $ctx != '' && $ctx != "\r\n") {
                $parts = $this->parsePart($ctx, $partNumber);

                foreach ($parts as $part) {
                    $finalParts[] = $part;
                    $partNumber = $part->partNumber;
                }

                $partNumber++;
            }
        }

        return $finalParts;
    }

    /**
     * Find all available parts.
     */
    public function findParts(): array
    {
        if ($this->type === IMAP::MESSAGE_TYPE_MULTIPART) {
            if (($boundary = $this->header->getBoundary()) === null) {
                throw new MessageContentFetchingException('no content found', 0);
            }

            return $this->detectParts($boundary, $this->raw);
        }

        return [new Part($this->raw, $this->header)];
    }
}
