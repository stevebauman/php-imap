<?php

namespace Tests\Issue;

use Tests\InteractsWithFixtures;
use Tests\TestCase;

class Issue382Test extends TestCase
{
    use InteractsWithFixtures;

    public function test_issue_email()
    {
        $message = $this->getFixture('issue-382.eml');

        $from = $message->from->first();

        $this->assertSame('Mail Delivery System', $from->personal);
        $this->assertSame('MAILER-DAEMON', $from->mailbox);
        $this->assertSame('mta-09.someserver.com', $from->host);
        $this->assertSame('MAILER-DAEMON@mta-09.someserver.com', $from->mail);
        $this->assertSame('Mail Delivery System <MAILER-DAEMON@mta-09.someserver.com>', $from->full);
    }
}
