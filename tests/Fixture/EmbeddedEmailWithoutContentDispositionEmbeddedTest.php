<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class EmbeddedEmailWithoutContentDispositionEmbeddedTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('embedded_email_without_content_disposition-embedded.eml');

        $this->assertEquals('embedded_message_subject', $message->subject);
        $this->assertEquals([
            'from webmail.my-office.cz (localhost [127.0.0.1]) by keira.cofis.cz ; Fri, 29 Jan 2016 14:25:40 +0100',
            'from webmail.my-office.cz (localhost [127.0.0.1]) by keira.cofis.cz',
        ], $message->received->toArray());
        $this->assertEquals('AC39946EBF5C034B87BABD5343E96979012671D40E38@VM002.cerk.cc', $message->message_id);
        $this->assertEquals('pl-PL, nl-NL', $message->accept_language);
        $this->assertEquals('pl-PL', $message->content_language);
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertEquals('some txt', $message->getTextBody());
        $this->assertEquals("<html>\r\n <p>some txt</p>\r\n</html>", $message->getHTMLBody());

        $this->assertEquals('2019-04-05 10:10:49', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('demo@cerstor.cz', $message->from);
        $this->assertEquals('demo@cerstor.cz', $message->to);

        $attachments = $message->getAttachments();
        $this->assertCount(2, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('file1.xlsx', $attachment->name);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('xlsx', $attachment->getExtension());
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $attachment->content_type);
        $this->assertEquals('87737d24c106b96e177f9564af6712e2c6d3e932c0632bfbab69c88b0bb934dc', hash('sha256', $attachment->content));
        $this->assertEquals(40, $attachment->size);
        $this->assertEquals(3, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[1];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('file2.xlsx', $attachment->name);
        $this->assertEquals('xlsx', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $attachment->content_type);
        $this->assertEquals('87737d24c106b96e177f9564af6712e2c6d3e932c0632bfbab69c88b0bb934dc', hash('sha256', $attachment->content));
        $this->assertEquals(40, $attachment->size);
        $this->assertEquals(4, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
