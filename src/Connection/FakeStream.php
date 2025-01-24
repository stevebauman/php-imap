<?php

namespace Webklex\PHPIMAP\Connection;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use RuntimeException;

class FakeStream implements StreamInterface
{
    /**
     * Lines queued for testing; each call to fgets() pops the next line.
     *
     * @var string[]
     */
    protected array $buffer = [];

    /**
     * Data that has been "written" to this fake stream (for assertion).
     *
     * @var string[]
     */
    protected array $written = [];

    /**
     * The connection info.
     */
    protected ?array $connection = null;

    /**
     * The mock meta info.
     */
    protected array $meta = [
        'crypto' => [
            'protocol' => '',
            'cipher_name' => '',
            'cipher_bits' => 0,
            'cipher_version' => '',
        ],
        'timed_out' => false,
        'blocked' => false,
        'eof' => false,
        'stream_type' => 'tcp_socket/unknown',
        'mode' => 'c',
        'unread_bytes' => 0,
        'seekable' => false,
    ];

    /**
     * Feed a line to the stream.
     */
    public function feed(array|string $lines): self
    {
        array_push($this->buffer, ...Arr::wrap($lines));

        return $this;
    }

    /**
     * Set the timed out status.
     */
    public function setMeta(string $attribute, mixed $value): self
    {
        if (! isset($this->meta[$attribute])) {
            throw new RuntimeException(
                "Unknown metadata attribute: {$attribute}"
            );
        }

        if (gettype($this->meta[$attribute]) !== gettype($value)) {
            throw new RuntimeException(
                "Metadata attribute {$attribute} must be of type ".gettype($this->meta[$attribute])
            );
        }

        Arr::set($this->meta, $attribute, $value);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function open(?string $transport = null, ?string $host = null, ?int $port = null, ?int $timeout = null): bool
    {
        $this->connection = compact('transport', 'host', 'port', 'timeout');

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->buffer = [];
        $this->connection = null;
    }

    /**
     * {@inheritDoc}
     */
    public function fgets(): string|false
    {
        if (! $this->isOpen()) {
            return false;
        }

        // Simulate timeout/eof checks.
        if ($this->meta['timed_out'] || $this->meta['eof']) {
            return false;
        }

        return array_shift($this->buffer) ?: false;
    }

    /**
     * {@inheritDoc}
     */
    public function fwrite(string $data): int|false
    {
        if (! $this->isOpen()) {
            return false;
        }

        $this->written[] = $data;

        return strlen($data);
    }

    /**
     * {@inheritDoc}
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * {@inheritDoc}
     */
    public function isOpen(): bool
    {
        return (bool) $this->connection;
    }

    /**
     * {@inheritDoc}
     */
    public function setTimeout(int $seconds): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setSocketSetCrypto(bool $enabled, ?int $method): bool|int
    {
        return true;
    }

    /**
     * Assert that the given data was written to the stream.
     */
    public function assertOutputContains(string $string): void
    {
        $found = false;

        foreach ($this->written as $index => $written) {
            if (Str::contains($written, $string)) {
                unset($this->written[$index]);

                $found = true;

                break;
            }
        }

        Assert::assertTrue($found, "Failed asserting that the string '{$string}' was written to the stream.");
    }
}
