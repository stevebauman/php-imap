<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Support\AttachmentCollection;

class MultipleNestedAttachmentsTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('multiple_nested_attachments.eml');

        $this->assertEquals('', $message->subject);
        $this->assertEquals('------------------------------------------------------------------------', $message->getTextBody());
        $this->assertEquals("<html>\r\n  <head>\r\n\r\n    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\">\r\n  </head>\r\n  <body text=\"#000000\" bgcolor=\"#FFFFFF\">\r\n    <p><br>\r\n    </p>\r\n    <div class=\"moz-signature\">\r\n      <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\">\r\n      <title></title>\r\n      Â <img src=\"cid:part1.8B953FBA.0E5A242C@xyz.xyz\" alt=\"\">\r\n      <hr>\r\n      <table width=\"20\" cellspacing=\"2\" cellpadding=\"2\" height=\"31\">\r\n        <tbody>\r\n          <tr>\r\n            <td><br>\r\n            </td>\r\n            <td valign=\"middle\"><br>\r\n            </td>\r\n          </tr>\r\n        </tbody>\r\n      </table>\r\n    </div>\r\n  </body>\r\n</html>", $message->getHTMLBody());

        $this->assertEquals('2018-01-15 09:54:09', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertFalse($message->from->first());
        $this->assertFalse($message->to->first());

        $attachments = $message->attachments();
        $this->assertInstanceOf(AttachmentCollection::class, $attachments);
        $this->assertCount(2, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('mleokdgdlgkkecep.png', $attachment->name);
        $this->assertEquals('png', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('image/png', $attachment->content_type);
        $this->assertEquals('e0e99b0bd6d5ea3ced99add53cc98b6f8eea6eae8ddd773fd06f3489289385fb', hash('sha256', $attachment->content));
        $this->assertEquals(114, $attachment->size);
        $this->assertEquals(5, $attachment->part_number);
        $this->assertEquals('inline', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[1];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('FF4D00-1.png', $attachment->name);
        $this->assertEquals('png', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('image/png', $attachment->content_type);
        $this->assertEquals('e0e99b0bd6d5ea3ced99add53cc98b6f8eea6eae8ddd773fd06f3489289385fb', hash('sha256', $attachment->content));
        $this->assertEquals(114, $attachment->size);
        $this->assertEquals(8, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
