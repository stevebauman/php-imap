<?php

namespace Webklex\PHPIMAP\Connection\Protocols;

use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\IMAP;

abstract class Protocol implements ProtocolInterface
{
    /**
     * The stream resource.
     *
     * @var resource
     */
    public mixed $stream = false;

    /**
     * Whether to debugging is enabled.
     */
    protected bool $debug = false;

    /**
     * Enable or disable the uid cache.
     */
    protected bool $enableUidCache = true;

    /**
     * The UID cache of active folder.
     */
    protected array $uidCache = [];

    /**
     * Connection encryption method.
     */
    protected ?string $encryption = null;

    /**
     * Default connection timeout in seconds.
     */
    protected int $connectionTimeout = 30;

    /**
     * Whether certificate validation is enabled.
     */
    protected bool $certValidation = true;

    /**
     * The connection proxy settings.
     */
    protected array $proxy = [
        'socket' => null,
        'request_fulluri' => false,
        'username' => null,
        'password' => null,
    ];

    /**
     * Get an available cryptographic method.
     */
    public function getCryptoMethod(): int
    {
        // Allow the best TLS version(s) we can
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;

        // PHP 5.6.7 dropped inclusion of TLS 1.1 and 1.2 in STREAM_CRYPTO_METHOD_TLS_CLIENT
        // so add them back in manually if we can
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        } elseif (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }

        return $cryptoMethod;
    }

    /**
     * Enable SSL certificate validation.
     */
    public function enableCertValidation(): Protocol
    {
        $this->certValidation = true;

        return $this;
    }

    /**
     * Disable SSL certificate validation.
     */
    public function disableCertValidation(): Protocol
    {
        $this->certValidation = false;

        return $this;
    }

    /**
     * Set SSL certificate validation.
     */
    public function setCertValidation(int $certValidation): Protocol
    {
        $this->certValidation = $certValidation;

        return $this;
    }

    /**
     * Should we validate SSL certificate?
     */
    public function getCertValidation(): bool
    {
        return $this->certValidation;
    }

    /**
     * Set connection proxy settings.
     */
    public function setProxy(array $options): Protocol
    {
        foreach ($this->proxy as $key => $val) {
            if (isset($options[$key])) {
                $this->proxy[$key] = $options[$key];
            }
        }

        return $this;
    }

    /**
     * Get the current proxy settings.
     */
    public function getProxy(): array
    {
        return $this->proxy;
    }

    /**
     * Prepare socket options.
     */
    private function defaultSocketOptions(string $transport): array
    {
        $options = [];

        if ($this->encryption) {
            $options['ssl'] = [
                'verify_peer_name' => $this->getCertValidation(),
                'verify_peer' => $this->getCertValidation(),
            ];
        }

        if ($this->proxy['socket'] != null) {
            $options[$transport]['proxy'] = $this->proxy['socket'];
            $options[$transport]['request_fulluri'] = $this->proxy['request_fulluri'];

            if ($this->proxy['username'] != null) {
                $auth = base64_encode($this->proxy['username'].':'.$this->proxy['password']);

                $options[$transport]['header'] = [
                    "Proxy-Authorization: Basic $auth",
                ];
            }
        }

        return $options;
    }

    /**
     * Create a new resource stream.
     *
     * @param  string  $host  hostname or IP address of IMAP server
     * @param  int  $port  of IMAP server, default is 143 (993 for ssl)
     * @param  int  $connectionTimeout  timeout in seconds for initiating session
     * @return resource The socket created.
     *
     * @throws ConnectionFailedException
     */
    public function createStream(string $transport, string $host, int $port, int $connectionTimeout): mixed
    {
        $stream = stream_socket_client(
            "$transport://$host:$port",
            $errno,
            $errstr,
            $connectionTimeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create($this->defaultSocketOptions($transport))
        );

        if (! $stream) {
            throw new ConnectionFailedException($errstr, $errno);
        }

        return $stream;
    }

    /**
     * Get the current connection timeout.
     */
    public function getConnectionTimeout(): int
    {
        return $this->connectionTimeout;
    }

    /**
     * Set the connection timeout.
     */
    public function setConnectionTimeout(int $connectionTimeout): Protocol
    {
        $this->connectionTimeout = $connectionTimeout;

        return $this;
    }

    /**
     * Set the stream timeout.
     */
    public function setStreamTimeout(int $streamTimeout): Protocol
    {
        if (stream_set_timeout($this->stream, $streamTimeout) === false) {
            throw new ConnectionFailedException('Failed to set stream timeout');
        }

        return $this;
    }

    /**
     * Get the UID key string.
     */
    public function getUIDKey(int|string $uid): string
    {
        if ($uid == IMAP::ST_UID || $uid == IMAP::FT_UID) {
            return 'UID';
        }

        if (strlen($uid) > 0 && ! is_numeric($uid)) {
            return (string) $uid;
        }

        return '';
    }

    /**
     * Build a UID / MSGN command.
     */
    public function buildUIDCommand(string $command, int|string $uid): string
    {
        return trim($this->getUIDKey($uid).' '.$command);
    }

    /**
     * Set the uid cache of current active folder.
     */
    public function setUidCache(?array $uids): void
    {
        if (is_null($uids)) {
            $this->uidCache = [];

            return;
        }

        $messageNumber = 1;

        $uid_cache = [];

        foreach ($uids as $uid) {
            $uid_cache[$messageNumber++] = (int) $uid;
        }

        $this->uidCache = $uid_cache;
    }

    /**
     * Enable the uid cache.
     */
    public function enableUidCache(): void
    {
        $this->enableUidCache = true;
    }

    /**
     * Disable the uid cache.
     */
    public function disableUidCache(): void
    {
        $this->enableUidCache = false;
    }

    /**
     * Set the encryption method.
     */
    public function setEncryption(string $encryption): void
    {
        $this->encryption = $encryption;
    }

    /**
     * Get the encryption method.
     */
    public function getEncryption(): ?string
    {
        return $this->encryption;
    }

    /**
     * Check if the current session is connected.
     */
    public function connected(): bool
    {
        return (bool) $this->stream;
    }

    /**
     * Retrieves header/meta data from the resource stream.
     */
    public function meta(): array
    {
        if (! $this->stream) {
            return [
                'crypto' => [
                    'protocol' => '',
                    'cipher_name' => '',
                    'cipher_bits' => 0,
                    'cipher_version' => '',
                ],
                'timed_out' => true,
                'blocked' => true,
                'eof' => true,
                'stream_type' => 'tcp_socket/unknown',
                'mode' => 'c',
                'unread_bytes' => 0,
                'seekable' => false,
            ];
        }

        return stream_get_meta_data($this->stream);
    }

    /**
     * Get the resource stream.
     */
    public function getStream(): mixed
    {
        return $this->stream;
    }
}
