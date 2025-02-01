<?php

namespace Tests\Issue;

use Tests\InteractsWithFixtures;
use Tests\TestCase;

class Issue412Test extends TestCase
{
    use InteractsWithFixtures;

    public function test_issue_email()
    {
        $message = $this->getMessageFixture('issue-412.eml');

        $this->assertSame('RE: TEST MESSAGE', (string) $message->subject);
        $this->assertSame('64254d63e92a36ee02c760676351e60a', md5($message->getTextBody()));
        $this->assertSame('2e4de288f6a1ed658548ed11fcdb1d79', md5($message->getHTMLBody()));
        $this->assertSame(0, $message->attachments()->count());
    }
}
