<?php

namespace Tests\Unit;

use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Connection\FakeStream;
use Webklex\PHPIMAP\Connection\ImapConnection;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

class FolderTest extends TestCase
{
    //    public function test_idle()
    //    {
    //        $stream = new FakeStream;
    //
    //        $stream->open();
    //
    //        $stream->feed([
    //            // Login
    //            '* OK IMAP4rev1 Service Ready',
    //            'TAG2 LOGIN',
    //            'TAG2 OK Logged in',
    //
    //            // Logout
    //            'TAG1 OK',
    //        ]);
    //
    //        $client = new Client;
    //
    //        $client->connect(new ImapConnection($stream));
    //
    //        $folder = new Folder($client, 'foo', '/', []);
    //
    //        $stream->feed([
    //            // Folder capabilities query.
    //            'TAG3 CAPABILITY',
    //            '* CAPABILITY IDLE',
    //            'TAG3 OK CAPABILITY completed',
    //
    //            // IDLE request.
    //            '* IDLE',
    //            '+ idling',
    //        ]);
    //
    //        $folder->idle(function (Message $message) {
    //            dd($message);
    //        });
    //
    //    }
}
