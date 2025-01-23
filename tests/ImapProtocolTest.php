<?php

namespace Tests;

use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Connection\Protocols\Response;

class ImapProtocolTest extends TestCase
{
    protected function stream(string $contents)
    {
        $stream = fopen('php://memory', 'rb+');

        fwrite($stream, $contents);

        rewind($stream);

        return $stream;
    }

    public function test_imap_protocol()
    {
        $protocol = new ImapProtocol(false);

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

        $protocol = new ImapProtocol;
        $protocol->stream = $this->stream($fixture);

        $response = new Response;

        $line = $protocol->nextLine($response);

        $this->assertSame('* OK IMAP4rev1 Service Ready', $line);
        $this->assertCount(1, $response->getResponse());
        $this->assertSame($fixture, implode('', $response->getResponse()));
    }

    public function test_next_line_multi_line_fixture()
    {
        $fixture = <<<'TEXT'
        * OK Dovecot ready.
        * CAPABILITY IMAP4rev1 UIDPLUS
        1 OK CAPABILITY completed
        TEXT;

        $protocol = new ImapProtocol;
        $protocol->stream = $this->stream($fixture);

        $response = new Response;

        $line1 = $protocol->nextLine($response);
        $this->assertSame("* OK Dovecot ready.\n", $line1);

        $line2 = $protocol->nextLine($response);
        $this->assertSame("* CAPABILITY IMAP4rev1 UIDPLUS\n", $line2);

        $line3 = $protocol->nextLine($response);
        $this->assertSame('1 OK CAPABILITY completed', $line3);

        $this->assertCount(3, $response->getResponse());
    }
}
