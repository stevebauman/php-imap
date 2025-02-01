<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class EmbeddedEmailWithoutContentDispositionTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getMessageFixture('embedded_email_without_content_disposition.eml');

        $this->assertEquals('Subject', $message->subject);
        $this->assertEquals([
            'from webmail.my-office.cz (localhost [127.0.0.1]) by keira.cofis.cz ; Fri, 29 Jan 2016 14:25:40 +0100',
        ], $message->received->toArray());
        $this->assertEquals('AC39946EBF5C034B87BABD5343E96979012671D9F7E4@VM002.cerk.cc', $message->message_id);
        $this->assertEquals('pl-PL, nl-NL', $message->accept_language);
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertEquals("TexT\r\n\r\n[cid:file.jpg]", $message->getTextBody());
        $this->assertEquals('<html><p>TexT</p></html>', $message->getHTMLBody());

        $this->assertEquals('2019-04-05 11:48:50', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('demo@cerstor.cz', $message->from);
        $this->assertEquals('demo@cerstor.cz', $message->to);

        $attachments = $message->getAttachments();
        $this->assertCount(4, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('file.jpg', $attachment->name);
        $this->assertEquals('jpg', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('image/jpeg', $attachment->content_type);
        $this->assertEquals('6b7fa434f92a8b80aab02d9bf1a12e49ffcae424e4013a1c4f68b67e3d2bbcd0', hash('sha256', $attachment->content));
        $this->assertEquals(96, $attachment->size);
        $this->assertEquals(3, $attachment->part_number);
        $this->assertEquals('inline', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[1];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('a1abc19a', $attachment->name);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('eml', $attachment->getExtension());
        $this->assertEquals('message/rfc822', $attachment->content_type);
        $this->assertEquals('2476c8b91a93c6b2fe1bfff593cb55956c2fe8e7ca6de9ad2dc9d101efe7a867', hash('sha256', $attachment->content));
        $this->assertEquals(2073, $attachment->size);
        $this->assertEquals(5, $attachment->part_number);
        $this->assertNull($attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[2];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('file3.xlsx', $attachment->name);
        $this->assertEquals('xlsx', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $attachment->content_type);
        $this->assertEquals('87737d24c106b96e177f9564af6712e2c6d3e932c0632bfbab69c88b0bb934dc', hash('sha256', $attachment->content));
        $this->assertEquals(40, $attachment->size);
        $this->assertEquals(6, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[3];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('file4.zip', $attachment->name);
        $this->assertEquals('zip', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/x-zip-compressed', $attachment->content_type);
        $this->assertEquals('87737d24c106b96e177f9564af6712e2c6d3e932c0632bfbab69c88b0bb934dc', hash('sha256', $attachment->content));
        $this->assertEquals(40, $attachment->size);
        $this->assertEquals(7, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
