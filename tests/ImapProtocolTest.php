<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;

class ImapProtocolTest extends TestCase
{
    public function test_imap_protocol(): void
    {
        $protocol = new ImapProtocol(false);
        self::assertSame(false, $protocol->getCertValidation());
        self::assertNull($protocol->getEncryption());

        $protocol->setCertValidation(true);
        $protocol->setEncryption('ssl');

        self::assertSame(true, $protocol->getCertValidation());
        self::assertSame('ssl', $protocol->getEncryption());
    }
}
