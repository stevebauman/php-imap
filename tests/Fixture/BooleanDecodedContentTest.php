<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class BooleanDecodedContentTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('boolean_decoded_content.eml');

        $this->assertEquals('Nuu', $message->subject);
        $this->assertEquals("Here is the problem mail\r\n \r\nBody text", $message->getTextBody());
        $this->assertEquals("Here is the problem mail\r\n \r\nBody text", $message->getHTMLBody());

        $this->assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from);
        $this->assertEquals('to@here.com', $message->to);

        $attachments = $message->getAttachments();
        $this->assertCount(1, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('Example Domain.pdf', $attachment->name);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('pdf', $attachment->getExtension());
        $this->assertEquals('application/pdf', $attachment->content_type);
        $this->assertEquals('1c449aaab4f509012fa5eaa180fd017eb7724ccacabdffc1c6066d3756dcde5c', hash('sha256', $attachment->content));
        $this->assertEquals(53, $attachment->size);
        $this->assertEquals(3, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
