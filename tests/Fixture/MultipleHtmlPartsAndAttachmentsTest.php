<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Support\AttachmentCollection;

class MultipleHtmlPartsAndAttachmentsTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('multiple_html_parts_and_attachments.eml');

        $this->assertEquals('multiple_html_parts_and_attachments', $message->subject);
        $this->assertEquals("This is the first html part\r\n\r\n￼\r\n\r\nThis is the second html part\r\n\r\n￼\r\n\r\nThis is the last html part\r\nhttps://www.there.com", $message->getTextBody());
        $this->assertEquals("<html><head><meta http-equiv=\"content-type\" content=\"text/html; charset=us-ascii\"></head><body style=\"overflow-wrap: break-word; -webkit-nbsp-mode: space; line-break: after-white-space;\">This is the <b>first</b> html <u>part</u><br><br></body></html>\n<html><body style=\"overflow-wrap: break-word; -webkit-nbsp-mode: space; line-break: after-white-space;\"><head><meta http-equiv=\"content-type\" content=\"text/html; charset=us-ascii\"></head><br><br>This is <strike>the</strike> second html <i>part</i><br><br></body></html>\n<html><head><meta http-equiv=\"content-type\" content=\"text/html; charset=us-ascii\"></head><body style=\"overflow-wrap: break-word; -webkit-nbsp-mode: space; line-break: after-white-space;\"><br><br><font size=\"2\"><i>This</i> is the last <b>html</b> part</font><div>https://www.there.com</div><div><br></div><br><br>\r\n<br></body></html>", $message->getHTMLBody());

        $this->assertEquals('2023-02-16 09:19:02', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));

        $from = $message->from->first();
        $this->assertEquals('FromName', $from->personal);
        $this->assertEquals('from', $from->mailbox);
        $this->assertEquals('there.com', $from->host);
        $this->assertEquals('from@there.com', $from->mail);
        $this->assertEquals('FromName <from@there.com>', $from->full);

        $this->assertEquals('to@there.com', $message->to->first());

        $attachments = $message->attachments();
        $this->assertInstanceOf(AttachmentCollection::class, $attachments);
        $this->assertCount(2, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('attachment1.pdf', $attachment->name);
        $this->assertEquals('pdf', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/pdf', $attachment->content_type);
        $this->assertEquals('c162adf19e0f67e26ef0b7f791b33a60b2c23b175560a505dc7f9ec490206e49', hash('sha256', $attachment->content));
        $this->assertEquals(4814, $attachment->size);
        $this->assertEquals(4, $attachment->part_number);
        $this->assertEquals('inline', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[1];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('attachment2.pdf', $attachment->name);
        $this->assertEquals('pdf', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('application/pdf', $attachment->content_type);
        $this->assertEquals('a337b37e9d3edb172a249639919f0eee3d344db352046d15f8f9887e55855a25', hash('sha256', $attachment->content));
        $this->assertEquals(5090, $attachment->size);
        $this->assertEquals(6, $attachment->part_number);
        $this->assertEquals('inline', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
