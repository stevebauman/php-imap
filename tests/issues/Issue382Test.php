<?php

namespace Tests\issues;

use Tests\TestCase;
use Webklex\PHPIMAP\Message;

class Issue382Test extends TestCase
{
    public function test_issue_email()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-382.eml']);
        $message = Message::fromFile($filename);

        $from = $message->from->first();

        $this->assertSame('Mail Delivery System', $from->personal);
        $this->assertSame('MAILER-DAEMON', $from->mailbox);
        $this->assertSame('mta-09.someserver.com', $from->host);
        $this->assertSame('MAILER-DAEMON@mta-09.someserver.com', $from->mail);
        $this->assertSame('Mail Delivery System <MAILER-DAEMON@mta-09.someserver.com>', $from->full);
    }
}
