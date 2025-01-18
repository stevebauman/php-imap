<?php

namespace Tests\issues;

use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Message;

class Issue382Test extends TestCase
{
    public function test_issue_email()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-382.eml']);
        $message = Message::fromFile($filename);

        $from = $message->from->first();

        self::assertSame('Mail Delivery System', $from->personal);
        self::assertSame('MAILER-DAEMON', $from->mailbox);
        self::assertSame('mta-09.someserver.com', $from->host);
        self::assertSame('MAILER-DAEMON@mta-09.someserver.com', $from->mail);
        self::assertSame('Mail Delivery System <MAILER-DAEMON@mta-09.someserver.com>', $from->full);
    }
}
