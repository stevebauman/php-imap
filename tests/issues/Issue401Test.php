<?php

namespace Tests\issues;

use Tests\TestCase;
use Webklex\PHPIMAP\Message;

class Issue401Test extends TestCase
{
    public function test_issue_email()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-401.eml']);
        $message = Message::fromFile($filename);

        $this->assertSame('1;00pm Client running few minutes late', (string) $message->subject);
    }
}
