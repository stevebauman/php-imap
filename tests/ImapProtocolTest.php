<?php

namespace Tests;

use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;

class ImapProtocolTest extends TestCase
{
    public function test_imap_protocol(): void
    {
        $protocol = new ImapProtocol(false);
        $this->assertSame(false, $protocol->getCertValidation());
        $this->assertNull($protocol->getEncryption());

        $protocol->setCertValidation(true);
        $protocol->setEncryption('ssl');

        $this->assertSame(true, $protocol->getCertValidation());
        $this->assertSame('ssl', $protocol->getEncryption());
    }
}
