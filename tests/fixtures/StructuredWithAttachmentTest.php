<?php

namespace Tests\fixtures;

use Webklex\PHPIMAP\Attachment;

class StructuredWithAttachmentTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('structured_with_attachment.eml');

        self::assertEquals('Test', $message->getSubject());
        self::assertEquals('Test', $message->getTextBody());
        self::assertFalse($message->hasHTMLBody());

        self::assertEquals('2017-09-29 08:55:23', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from->first()->mail);
        self::assertEquals('to@here.com', $message->to->first()->mail);

        self::assertCount(1, $message->attachments());

        $attachment = $message->attachments()->first();
        self::assertInstanceOf(Attachment::class, $attachment);
        self::assertEquals('MyFile.txt', $attachment->name);
        self::assertEquals('txt', $attachment->getExtension());
        self::assertEquals('text', $attachment->type);
        self::assertEquals('text/plain', $attachment->content_type);
        self::assertEquals('MyFileContent', $attachment->content);
        self::assertEquals(20, $attachment->size);
        self::assertEquals(2, $attachment->part_number);
        self::assertEquals('attachment', $attachment->disposition);
        self::assertNotEmpty($attachment->id);
    }
}
