<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class MailThatIsAttachmentTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getMessageFixture('mail_that_is_attachment.eml');

        $this->assertEquals('Report domain: yyy.cz Submitter: google.com Report-ID: 2244696771454641389', $message->subject);
        $this->assertEquals('2244696771454641389@google.com', $message->message_id);
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertFalse($message->hasTextBody());
        $this->assertFalse($message->hasHTMLBody());

        $this->assertEquals('2015-02-15 10:21:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('xxx@yyy.cz', $message->to->first()->mail);
        $this->assertEquals('xxx@yyy.cz', $message->sender->first()->mail);

        $from = $message->from->first();
        $this->assertEquals('noreply-dmarc-support via xxx', $from->personal);
        $this->assertEquals('xxx', $from->mailbox);
        $this->assertEquals('yyy.cz', $from->host);
        $this->assertEquals('xxx@yyy.cz', $from->mail);
        $this->assertEquals('noreply-dmarc-support via xxx <xxx@yyy.cz>', $from->full);

        $this->assertCount(1, $message->attachments());

        $attachment = $message->attachments()->first();
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('google.com!yyy.cz!1423872000!1423958399.zip', $attachment->name);
        $this->assertEquals('zip', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/zip', $attachment->content_type);
        $this->assertEquals('c0d4f47b6fde124cea7460c3e509440d1a062705f550b0502b8ba0cbf621c97a', hash('sha256', $attachment->content));
        $this->assertEquals(1062, $attachment->size);
        $this->assertEquals(0, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
