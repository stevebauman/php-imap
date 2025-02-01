<?php

namespace Tests\Issue;

use Tests\InteractsWithFixtures;
use Tests\TestCase;

class Issue414Test extends TestCase
{
    use InteractsWithFixtures;

    public function test_issue_email()
    {
        $message = $this->getFixture('issue-414.eml');

        $this->assertSame('Test', (string) $message->subject);

        $attachments = $message->getAttachments();

        $this->assertSame(2, $attachments->count());

        $attachment = $attachments->first();

        $this->assertEmpty($attachment->description);
        $this->assertSame('MyFile.txt', $attachment->filename);
        $this->assertSame('MyFile.txt', $attachment->name);
        $this->assertSame('be62f7e6', $attachment->id);

        $attachment = $attachments->last();
        $this->assertEmpty($attachment->description);
        $this->assertSame('foo', $attachment->filename);
        $this->assertSame('foo', $attachment->name);
        $this->assertSame('12e1d38b', $attachment->hash);
    }
}
