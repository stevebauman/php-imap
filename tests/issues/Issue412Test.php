<?php

namespace Tests\issues;

use Tests\TestCase;
use Webklex\PHPIMAP\Message;

class Issue412Test extends TestCase
{
    public function test_issue_email()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-412.eml']);
        $message = Message::fromFile($filename);

        $this->assertSame('RE: TEST MESSAGE', (string) $message->subject);
        $this->assertSame('64254d63e92a36ee02c760676351e60a', md5($message->getTextBody()));
        $this->assertSame('2e4de288f6a1ed658548ed11fcdb1d79', md5($message->getHTMLBody()));
        $this->assertSame(0, $message->attachments()->count());
    }
}
