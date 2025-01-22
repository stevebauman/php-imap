<?php

namespace Tests\issues;

use Tests\TestCase;
use Webklex\PHPIMAP\Message;

class Issue275Test extends TestCase
{
    public function test_issue_email1()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-275.eml']);
        $message = Message::fromFile($filename);

        self::assertSame('Testing 123', (string) $message->subject);
        self::assertSame('Asdf testing123 this is a body', $message->getTextBody());
    }

    public function test_issue_email2()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-275-2.eml']);
        $message = Message::fromFile($filename);

        $body = "Test\r\n\r\nMed venlig hilsen\r\nMartin Larsen\r\nFeline Holidays A/S\r\nTlf 78 77 04 12";

        self::assertSame('Test 1017', (string) $message->subject);
        self::assertSame($body, $message->getTextBody());
    }
}
