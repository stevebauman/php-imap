<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class MixedFilenameTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('mixed_filename.eml');

        $this->assertEquals('Свежий прайс-лист', $message->subject);
        $this->assertFalse($message->hasTextBody());
        $this->assertFalse($message->hasHTMLBody());

        $this->assertEquals('2018-02-02 19:23:06', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));

        $from = $message->from->first();
        $this->assertEquals('Прайсы || ПартКом', $from->personal);
        $this->assertEquals('support', $from->mailbox);
        $this->assertEquals('part-kom.ru', $from->host);
        $this->assertEquals('support@part-kom.ru', $from->mail);
        $this->assertEquals('Прайсы || ПартКом <support@part-kom.ru>', $from->full);

        $this->assertEquals('foo@bar.com', $message->to->first());

        $this->assertCount(1, $message->attachments());

        $attachment = $message->attachments()->first();
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('Price4VladDaKar.xlsx', $attachment->name);
        $this->assertEquals('xlsx', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/octet-stream', $attachment->content_type);
        $this->assertEquals('b832983842b0ad65db69e4c7096444c540a2393e2d43f70c2c9b8b9fceeedbb1', hash('sha256', $attachment->content));
        $this->assertEquals(94, $attachment->size);
        $this->assertEquals(2, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
