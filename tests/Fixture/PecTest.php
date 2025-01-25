<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Support\AttachmentCollection;

class PecTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('pec.eml');

        $this->assertEquals('Certified', $message->subject);
        $this->assertEquals('Signed', $message->getTextBody());
        $this->assertEquals('<html><body>Signed</body></html>', $message->getHTMLBody());

        $this->assertEquals('2017-10-02 10:13:43', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('test@example.com', $message->from->first()->mail);
        $this->assertEquals('test@example.com', $message->to->first()->mail);

        $attachments = $message->attachments();

        $this->assertInstanceOf(AttachmentCollection::class, $attachments);
        $this->assertCount(3, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('data.xml', $attachment->name);
        $this->assertEquals('xml', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/xml', $attachment->content_type);
        $this->assertEquals('<xml/>', $attachment->content);
        $this->assertEquals(8, $attachment->size);
        $this->assertEquals(4, $attachment->part_number);
        $this->assertEquals('inline', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[1];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('postacert.eml', $attachment->name);
        $this->assertEquals('eml', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('message/rfc822', $attachment->content_type);
        $this->assertEquals("To: test@example.com\r\nFrom: test@example.com\r\nSubject: test-subject\r\nDate: Mon, 2 Oct 2017 12:13:50 +0200\r\nContent-Type: text/plain; charset=iso-8859-15; format=flowed\r\nContent-Transfer-Encoding: 7bit\r\n\r\ntest-content", $attachment->content);
        $this->assertEquals(216, $attachment->size);
        $this->assertEquals(5, $attachment->part_number);
        $this->assertEquals('inline', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[2];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('smime.p7s', $attachment->name);
        $this->assertEquals('p7s', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/x-pkcs7-signature', $attachment->content_type);
        $this->assertEquals('1', $attachment->content);
        $this->assertEquals(4, $attachment->size);
        $this->assertEquals(7, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
