<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class PlainTextAttachmentTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('plain_text_attachment.eml');

        $this->assertEquals('Plain text attachment', $message->subject);
        $this->assertEquals('Test', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());

        $this->assertEquals('2018-08-21 07:05:14', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from->first()->mail);
        $this->assertEquals('to@here.com', $message->to->first()->mail);

        $this->assertCount(1, $message->attachments());

        $attachment = $message->attachments()->first();
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('a.txt', $attachment->name);
        $this->assertEquals('txt', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertNull($attachment->content_type);
        $this->assertEquals('Hi!', $attachment->content);
        $this->assertEquals(4, $attachment->size);
        $this->assertEquals(2, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
