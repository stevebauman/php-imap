<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class StructuredWithAttachmentTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('structured_with_attachment.eml');

        $this->assertEquals('Test', $message->getSubject());
        $this->assertEquals('Test', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());

        $this->assertEquals('2017-09-29 08:55:23', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from->first()->mail);
        $this->assertEquals('to@here.com', $message->to->first()->mail);

        $this->assertCount(1, $message->attachments());

        $attachment = $message->attachments()->first();
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('MyFile.txt', $attachment->name);
        $this->assertEquals('txt', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('text/plain', $attachment->content_type);
        $this->assertEquals('MyFileContent', $attachment->content);
        $this->assertEquals(20, $attachment->size);
        $this->assertEquals(2, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
