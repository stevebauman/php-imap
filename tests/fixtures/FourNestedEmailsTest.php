<?php

namespace Tests\fixtures;

use Webklex\PHPIMAP\Attachment;

class FourNestedEmailsTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('four_nested_emails.eml');

        $this->assertEquals('3-third-subject', $message->subject);
        $this->assertEquals('3-third-content', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertFalse($message->date->first());
        $this->assertEquals('test@example.com', $message->from->first()->mail);
        $this->assertEquals('test@example.com', $message->to->first()->mail);

        $attachments = $message->getAttachments();
        $this->assertCount(1, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('2-second-email.eml', $attachment->name);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('eml', $attachment->getExtension());
        $this->assertEquals('message/rfc822', $attachment->content_type);
        $this->assertEquals('85012e6a26d064a0288ee62618b3192687385adb4a4e27e48a28f738a325ca46', hash('sha256', $attachment->content));
        $this->assertEquals(1376, $attachment->size);
        $this->assertEquals(2, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
