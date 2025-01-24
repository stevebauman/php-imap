<?php

namespace Tests;

use Webklex\PHPIMAP\Connection\FakeStream;
use Webklex\PHPIMAP\Connection\ImapConnection;
use Webklex\PHPIMAP\Connection\Response;

class ImapConnectionTest extends TestCase
{
    public function test_imap_protocol()
    {
        $protocol = new ImapConnection(certValidation: false);

        $this->assertSame(false, $protocol->getCertValidation());
        $this->assertNull($protocol->getEncryption());

        $protocol->setCertValidation(true);
        $protocol->setEncryption('ssl');

        $this->assertSame(true, $protocol->getCertValidation());
        $this->assertSame('ssl', $protocol->getEncryption());
    }

    public function test_next_line_reads_line_successfully()
    {
        $fixture = '* OK IMAP4rev1 Service Ready';

        $stream = (new FakeStream);

        $stream->open();

        $stream->feed($fixture);

        $protocol = new ImapConnection($stream);

        $response = new Response;

        $line = $protocol->nextLine($response);

        $this->assertSame('* OK IMAP4rev1 Service Ready', $line);
        $this->assertCount(1, $response->getResponse());
        $this->assertSame($fixture, implode('', $response->getResponse()));
    }

    public function test_next_line_multi_line_fixture()
    {
        $stream = (new FakeStream);

        $stream->open();

        $stream->feed([
            '* OK Dovecot ready.',
            '* CAPABILITY IMAP4rev1 UIDPLUS',
            '1 OK CAPABILITY completed',
        ]);

        $protocol = new ImapConnection($stream);

        $response = new Response;

        $line1 = $protocol->nextLine($response);
        $this->assertSame('* OK Dovecot ready.', $line1);

        $line2 = $protocol->nextLine($response);
        $this->assertSame('* CAPABILITY IMAP4rev1 UIDPLUS', $line2);

        $line3 = $protocol->nextLine($response);
        $this->assertSame('1 OK CAPABILITY completed', $line3);

        $this->assertCount(3, $response->getResponse());
    }
}
