<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Support\AttachmentCollection;

class InlineAttachmentTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('inline_attachment.eml');

        $this->assertEquals('', $message->subject);
        $this->assertFalse($message->hasTextBody());
        $this->assertEquals('<img style="height: auto;" src="cid:ii_15f0aad691bb745f" border="0"/>', $message->getHTMLBody());

        $this->assertFalse($message->date->first());
        $this->assertFalse($message->from->first());
        $this->assertFalse($message->to->first());

        $attachments = $message->attachments();
        $this->assertInstanceOf(AttachmentCollection::class, $attachments);
        $this->assertCount(1, $attachments);

        $attachment = $attachments[0];

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('d2913999', $attachment->name);
        $this->assertEquals('d2913999', $attachment->filename);
        $this->assertEquals('ii_15f0aad691bb745f', $attachment->id);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('', $attachment->getExtension());
        $this->assertEquals('image/png', $attachment->content_type);
        $this->assertEquals('6568c9e9c35a7fa06f236e89f704d8c9b47183a24f2c978dba6c92e2747e3a13', hash('sha256', $attachment->content));
        $this->assertEquals(1486, $attachment->size);
        $this->assertEquals(1, $attachment->part_number);
        $this->assertEquals('inline', $attachment->disposition);
        $this->assertEquals('<ii_15f0aad691bb745f>', $attachment->content_id);
        $this->assertNotEmpty($attachment->id);
    }
}
