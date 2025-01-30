<?php

namespace Tests\Issue;

use Tests\InteractsWithFixtures;
use Tests\TestCase;

class Issue275Test extends TestCase
{
    use InteractsWithFixtures;

    public function test_issue_email1()
    {
        $message = $this->getFixture('issue-275.eml');

        $this->assertSame('Testing 123', (string) $message->subject);
        $this->assertSame('Asdf testing123 this is a body', $message->getTextBody());
    }

    public function test_issue_email2()
    {
        $message = $this->getFixture('issue-275-2.eml');

        $body = "Test\r\n\r\nMed venlig hilsen\r\nMartin Larsen\r\nFeline Holidays A/S\r\nTlf 78 77 04 12";

        $this->assertSame('Test 1017', (string) $message->subject);
        $this->assertSame($body, $message->getTextBody());
    }
}
