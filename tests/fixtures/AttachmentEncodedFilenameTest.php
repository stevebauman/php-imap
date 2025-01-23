<?php

namespace Tests\fixtures;

use Webklex\PHPIMAP\Attachment;

class AttachmentEncodedFilenameTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('attachment_encoded_filename.eml');

        $this->assertEquals('', $message->subject);
        $this->assertEquals('multipart/mixed', $message->content_type->last());
        $this->assertFalse($message->hasTextBody());
        $this->assertFalse($message->hasHTMLBody());

        $this->assertCount(1, $message->attachments());

        $attachment = $message->attachments()->first();
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('Prostřeno_2014_poslední volné termíny.xls', $attachment->filename);
        $this->assertEquals('Prostřeno_2014_poslední volné termíny.xls', $attachment->name);
        $this->assertEquals('xls', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/vnd.ms-excel', $attachment->content_type);
        $this->assertEquals('a0ef7cfbc05b73dbcb298fe0bc224b41900cdaf60f9904e3fea5ba6c7670013c', hash('sha256', $attachment->content));
        $this->assertEquals(146, $attachment->size);
        $this->assertEquals(0, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
