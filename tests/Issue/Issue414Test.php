<?php

namespace Tests\Issue;

use Tests\TestCase;
use Webklex\PHPIMAP\Message;

class Issue414Test extends TestCase
{
    public function test_issue_email()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-414.eml']);
        $message = Message::fromFile($filename);

        $this->assertSame('Test', (string) $message->subject);

        $attachments = $message->getAttachments();

        $this->assertSame(2, $attachments->count());

        $attachment = $attachments->first();
        $this->assertEmpty($attachment->description);
        $this->assertSame('exampleMyFile.txt', $attachment->filename);
        $this->assertSame('exampleMyFile.txt', $attachment->name);
        $this->assertSame('be62f7e6', $attachment->id);

        $attachment = $attachments->last();
        $this->assertEmpty($attachment->description);
        $this->assertSame('phpfoo', $attachment->filename);
        $this->assertSame('phpfoo', $attachment->name);
        $this->assertSame('12e1d38b', $attachment->hash);
    }
}
