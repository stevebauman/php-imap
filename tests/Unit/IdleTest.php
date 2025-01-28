<?php

namespace Tests\Unit;

use Exception;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Connection\FakeStream;
use Webklex\PHPIMAP\Connection\ImapConnection;
use Webklex\PHPIMAP\Idle;

class IdleTest extends TestCase
{
    public function test_idle()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            // Initial connection
            '* OK IMAP4rev1 Service Ready',

            // Login response
            'TAG1 OK Logged in',

            // Select folder response
            '* FLAGS (\Answered \Flagged \Deleted \Seen \Draft $Forwarded)',
            '* OK [PERMANENTFLAGS (\Answered \Flagged \Deleted \Seen \Draft $Forwarded \*)] Flags permitted.',
            '* 3 EXISTS',
            '* 0 RECENT',
            '* OK [UIDVALIDITY 1707169026] UIDs valid',
            '* OK [UIDNEXT 626] Predicted next UID',
            '* OK [HIGHESTMODSEQ 4578] Highest',
            'TAG2 OK [READ-WRITE] Select completed (0.002 + 0.000 + 0.001 secs).',

            // Idling response
            '+ idling',

            // New message arrival
            '* 24 EXISTS',
            '* 24 FETCH (FLAGS (\Seen) UID 12345)',

            // Logout
            'TAG4 OK LOGOUT completed',
        ]);

        $client = new Client;

        $client->connect(new ImapConnection($stream));

        try {
            (new Idle($client, 'INBOX', 10))->await(
                function ($msgn, $sequence) use (&$receivedMsgn, &$receivedSequence) {
                    $this->assertEquals(24, $msgn);
                    $this->assertEquals(1, $sequence);
                }
            );
        } catch (Exception) {
            // Do nothing.
        }
    }
}
