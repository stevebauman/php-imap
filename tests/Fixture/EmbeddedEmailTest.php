<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class EmbeddedEmailTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('embedded_email.eml');

        $this->assertEquals('embedded message', $message->subject);
        $this->assertEquals([
            'from webmail.my-office.cz (localhost [127.0.0.1]) by keira.cofis.cz ; Fri, 29 Jan 2016 14:25:40 +0100',
            'from webmail.my-office.cz (localhost [127.0.0.1]) by keira.cofis.cz',
        ], $message->received->toArray());
        $this->assertEquals('7e5798da5747415e5b82fdce042ab2a6@cerstor.cz', $message->message_id);
        $this->assertEquals('demo@cerstor.cz', $message->return_path);
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertEquals('Roundcube Webmail/1.0.0', $message->user_agent);
        $this->assertEquals('email that contains embedded message', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());

        $this->assertEquals('2016-01-29 13:25:40', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('demo@cerstor.cz', $message->from);
        $this->assertEquals('demo@cerstor.cz', $message->x_sender);
        $this->assertEquals('demo@cerstor.cz', $message->to);

        $attachments = $message->getAttachments();
        $this->assertCount(1, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('demo.eml', $attachment->name);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('eml', $attachment->getExtension());
        $this->assertEquals('message/rfc822', $attachment->content_type);
        $this->assertEquals('a1f965f10a9872e902a82dde039a237e863f522d238a1cb1968fe3396dbcac65', hash('sha256', $attachment->content));
        $this->assertEquals(893, $attachment->size);
        $this->assertEquals(1, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
