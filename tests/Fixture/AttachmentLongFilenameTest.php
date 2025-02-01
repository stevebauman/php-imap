<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class AttachmentLongFilenameTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getMessageFixture('attachment_long_filename.eml');

        $this->assertEquals('', $message->subject);
        $this->assertEquals('multipart/mixed', $message->content_type->last());
        $this->assertFalse($message->hasTextBody());
        $this->assertFalse($message->hasHTMLBody());

        $attachments = $message->attachments();
        $this->assertCount(3, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('Buchungsbestätigung- Rechnung-Geschäftsbedingungen-Nr.B123-45 - XXXX xxxxxxxxxxxxxxxxx XxxX, Lüdxxxxxxxx - VM Klaus XXXXXX - xxxxxxxx.pdf', $attachment->name);
        $this->assertEquals('Buchungsbestätigung- Rechnung-Geschäftsbedingungen-Nr.B123-45 - XXXXX xxxxxxxxxxxxxxxxx XxxX, Lüxxxxxxxxxx - VM Klaus XXXXXX - xxxxxxxx.pdf', $attachment->filename);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('pdf', $attachment->getExtension());
        $this->assertEquals('text/plain', $attachment->content_type);
        $this->assertEquals('ca51ce1fb15acc6d69b8a5700256172fcc507e02073e6f19592e341bd6508ab8', hash('sha256', $attachment->content));
        $this->assertEquals(4, $attachment->size);
        $this->assertEquals(0, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[1];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('01_A€àäąбيد@Z-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz.txt', $attachment->name);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('txt', $attachment->getExtension());
        $this->assertEquals('text/plain', $attachment->content_type);
        $this->assertEquals('ca51ce1fb15acc6d69b8a5700256172fcc507e02073e6f19592e341bd6508ab8', hash('sha256', $attachment->content));
        $this->assertEquals(4, $attachment->size);
        $this->assertEquals(1, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[2];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('02_A€àäąбيد@Z-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz.txt', $attachment->name);
        $this->assertEquals('02_A€àäąбيد@Z-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz-0123456789-qwertyuiopasdfghjklzxcvbnmopqrstuvz.txt', $attachment->filename);
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('text/plain', $attachment->content_type);
        $this->assertEquals('txt', $attachment->getExtension());
        $this->assertEquals('ca51ce1fb15acc6d69b8a5700256172fcc507e02073e6f19592e341bd6508ab8', hash('sha256', $attachment->content));
        $this->assertEquals(4, $attachment->size);
        $this->assertEquals(2, $attachment->part_number);
        $this->assertEquals('attachment', $attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
