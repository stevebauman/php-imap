<?php

namespace Webklex\PHPIMAP;

use Carbon\Carbon;
use Webklex\PHPIMAP\Connection\Protocols\Response;
use Webklex\PHPIMAP\Exceptions\ConnectionClosedException;
use Webklex\PHPIMAP\Exceptions\ConnectionTimedOutException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;

class Idle
{
    /**
     * The idle IMAP client.
     */
    protected Client $client;

    /**
     * Constructor.
     */
    public function __construct(
        protected Folder $folder,
        protected int $timeout,
    ) {
        $this->client = clone $folder->getClient();
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->client->disconnect();
    }

    /**
     * Await new messages on the connection.
     */
    public function await(callable $callback): void
    {
        $this->connect();

        $this->idle();

        $ttl = $this->getNextTimeout();

        $sequence = ClientManager::get('options.sequence', Imap::ST_MSGN);

        while (true) {
            try {
                $line = $this->getNextLine();
            } catch (ConnectionTimedOutException) {
                $this->reidle();

                $ttl = $this->getNextTimeout();

                continue;
            } catch (ConnectionClosedException) {
                $this->reconnect();

                $ttl = $this->getNextTimeout();

                continue;
            }

            if (($pos = strpos($line, 'EXISTS')) !== false) {
                $msgn = (int) substr($line, 2, $pos - 2);

                $callback($msgn, $sequence);

                $ttl = $this->getNextTimeout();
            }

            if (! Carbon::now()->greaterThanOrEqualTo($ttl)) {
                continue;
            }

            try {
                // If we've been idle too long, we'll send a DONE and re-IDLE.
                // This will keep the server from killing the connection.
                // Some Servers require this to avoid disconnection.
                $this->done();
            } catch (RuntimeException) {
                // If done fails, we're likely already disconnected.
                // We'll attempt to reconnect and restart the IDLE.
                $this->reconnect();
            }

            $this->idle();

            $ttl = $this->getNextTimeout();
        }
    }

    /**
     * Reconnect the client and restart IDLE.
     */
    protected function reconnect(): void
    {
        $this->client->getConnection()->reset();

        $this->connect();
    }

    /**
     * Connect the client and begin IDLE.
     */
    protected function connect(): void
    {
        $this->client->connect();

        $this->client->openFolder($this->folder->path, true);

        $this->client->getConnection()->setStreamTimeout($this->timeout);
    }

    /**
     * End the current IDLE session and start a new one.
     */
    protected function reidle(): void
    {
        try {
            $this->done();
        } catch (RuntimeException) {
            $this->reconnect();
        }

        $this->idle();
    }

    /**
     * End the current IDLE session.
     */
    protected function done(): void
    {
        $this->client->getConnection()->done();
    }

    /**
     * Being a new IDLE session.
     */
    protected function idle(): void
    {
        $this->client->getConnection()->idle();
    }

    /**
     * Get the next line from the connection.
     *
     * @throws ConnectionTimedOutException|ConnectionClosedException
     */
    protected function getNextLine(): string
    {
        return $this->client->getConnection()->nextLine(Response::empty());
    }

    /**
     * Get the next timeout.
     */
    protected function getNextTimeout(): Carbon
    {
        return Carbon::now()->addSeconds($this->timeout);
    }
}
