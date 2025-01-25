<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Attachment;

class ExampleBounceTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('example_bounce.eml');

        $this->assertEquals('<>', $message->return_path);
        $this->assertEquals([
            0 => 'from somewhere.your-server.de by somewhere.your-server.de with LMTP id 3TP8LrElAGSOaAAAmBr1xw (envelope-from <>); Thu, 02 Mar 2023 05:27:29 +0100',
            1 => 'from somewhere06.your-server.de ([1b21:2f8:e0a:50e4::2]) by somewhere.your-server.de with esmtps  (TLS1.3) tls TLS_AES_256_GCM_SHA384 (Exim 4.94.2) id 1pXaXR-0006xQ-BN for demo@foo.de; Thu, 02 Mar 2023 05:27:29 +0100',
            2 => 'from [192.168.0.10] (helo=sslproxy01.your-server.de) by somewhere06.your-server.de with esmtps (TLSv1.3:TLS_AES_256_GCM_SHA384:256) (Exim 4.92) id 1pXaXO-000LYP-9R for demo@foo.de; Thu, 02 Mar 2023 05:27:26 +0100',
            3 => 'from localhost ([127.0.0.1] helo=sslproxy01.your-server.de) by sslproxy01.your-server.de with esmtps (TLSv1.3:TLS_AES_256_GCM_SHA384:256) (Exim 4.92) id 1pXaXO-0008gy-7x for demo@foo.de; Thu, 02 Mar 2023 05:27:26 +0100',
            4 => 'from Debian-exim by sslproxy01.your-server.de with local (Exim 4.92) id 1pXaXO-0008gb-6g for demo@foo.de; Thu, 02 Mar 2023 05:27:26 +0100',
            5 => 'from somewhere.your-server.de by somewhere.your-server.de with LMTP id 3TP8LrElAGSOaAAAmBr1xw (envelope-from <>)',
        ], $message->received->all());
        $this->assertEquals('demo@foo.de', $message->envelope_to);
        $this->assertEquals('Thu, 02 Mar 2023 05:27:29 +0100', $message->delivery_date);
        $this->assertEquals([
            0 => 'somewhere.your-server.de; iprev=pass (somewhere06.your-server.de) smtp.remote-ip=1b21:2f8:e0a:50e4::2; spf=none smtp.mailfrom=<>; dmarc=skipped',
            1 => 'somewhere.your-server.de',
        ], $message->authentication_results->all());
        $this->assertEquals([
            0 => 'from somewhere.your-server.de by somewhere.your-server.de with LMTP id 3TP8LrElAGSOaAAAmBr1xw (envelope-from <>); Thu, 02 Mar 2023 05:27:29 +0100',
            1 => 'from somewhere06.your-server.de ([1b21:2f8:e0a:50e4::2]) by somewhere.your-server.de with esmtps  (TLS1.3) tls TLS_AES_256_GCM_SHA384 (Exim 4.94.2) id 1pXaXR-0006xQ-BN for demo@foo.de; Thu, 02 Mar 2023 05:27:29 +0100',
            2 => 'from [192.168.0.10] (helo=sslproxy01.your-server.de) by somewhere06.your-server.de with esmtps (TLSv1.3:TLS_AES_256_GCM_SHA384:256) (Exim 4.92) id 1pXaXO-000LYP-9R for demo@foo.de; Thu, 02 Mar 2023 05:27:26 +0100',
            3 => 'from localhost ([127.0.0.1] helo=sslproxy01.your-server.de) by sslproxy01.your-server.de with esmtps (TLSv1.3:TLS_AES_256_GCM_SHA384:256) (Exim 4.92) id 1pXaXO-0008gy-7x for demo@foo.de; Thu, 02 Mar 2023 05:27:26 +0100',
            4 => 'from Debian-exim by sslproxy01.your-server.de with local (Exim 4.92) id 1pXaXO-0008gb-6g for demo@foo.de; Thu, 02 Mar 2023 05:27:26 +0100',
            5 => 'from somewhere.your-server.de by somewhere.your-server.de with LMTP id 3TP8LrElAGSOaAAAmBr1xw (envelope-from <>)',
        ], $message->received->all());
        $this->assertEquals('ding@ding.de', $message->x_failed_recipients);
        $this->assertEquals('auto-replied', $message->auto_submitted);
        $this->assertEquals('Mail Delivery System <Mailer-Daemon@sslproxy01.your-server.de>', $message->from);
        $this->assertEquals('demo@foo.de', $message->to);
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertEquals('Mail delivery failed', $message->subject);
        $this->assertEquals('E1pXaXO-0008gb-6g@sslproxy01.your-server.de', $message->message_id);
        $this->assertEquals('2023-03-02 04:27:26', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('Clear (ClamAV 0.103.8/26827/Wed Mar  1 09:28:49 2023)', $message->x_virus_scanned);
        $this->assertEquals('0.0 (/)', $message->x_spam_score);
        $this->assertEquals('bar-demo@foo.de', $message->delivered_to);
        $this->assertEquals('multipart/report', $message->content_type->last());
        $this->assertEquals('5d4847c21c8891e73d62c8246f260a46496958041a499f33ecd47444fdaa591b', hash('sha256', $message->getTextBody()));
        $this->assertFalse($message->hasHTMLBody());

        $attachments = $message->attachments();
        $this->assertCount(2, $attachments);

        $attachment = $attachments[0];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('c541a506', $attachment->filename);
        $this->assertEquals('c541a506', $attachment->name);
        $this->assertEquals('', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('message/delivery-status', $attachment->content_type);
        $this->assertEquals('85ac09d1d74b2d85853084dc22abcad205a6bfde62d6056e3a933ffe7e82e45c', hash('sha256', $attachment->content));
        $this->assertEquals(267, $attachment->size);
        $this->assertEquals(1, $attachment->part_number);
        $this->assertNull($attachment->disposition);
        $this->assertNotEmpty($attachment->id);

        $attachment = $attachments[1];
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('da786518', $attachment->filename);
        $this->assertEquals('da786518', $attachment->name);
        $this->assertEquals('', $attachment->getExtension());
        $this->assertEquals('text', $attachment->type);
        $this->assertEquals('message/rfc822', $attachment->content_type);
        $this->assertEquals('7525331f5fab23ea77f595b995336aca7b8dad12db00ada14abebe7fe5b96e10', hash('sha256', $attachment->content));
        $this->assertEquals(776, $attachment->size);
        $this->assertEquals(2, $attachment->part_number);
        $this->assertNull($attachment->disposition);
        $this->assertNotEmpty($attachment->id);
    }
}
