<?php

namespace Tests;

use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Part;

class PartTest extends TestCase
{
    public function test_text_part(): void
    {
        $raw_headers = "Content-Type: text/plain;\r\n charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n";
        $raw_body = "\r\nAny updates?";

        $headers = new Header($raw_headers);
        $part = new Part($raw_body, $headers, 0);

        $this->assertSame('UTF-8', $part->charset);
        $this->assertSame('text/plain', $part->content_type);
        $this->assertSame(12, $part->bytes);
        $this->assertSame(0, $part->part_number);
        $this->assertSame(false, $part->ifdisposition);
        $this->assertSame(false, $part->isAttachment());
        $this->assertSame('Any updates?', $part->content);
        $this->assertSame(IMAP::MESSAGE_TYPE_TEXT, $part->type);
        $this->assertSame(IMAP::MESSAGE_ENC_7BIT, $part->encoding);
    }

    public function test_html_part(): void
    {
        $raw_headers = "Content-Type: text/html;\r\n charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n";
        $raw_body = "\r\n<p></p>\r\n<p dir=\"auto\">Any updates?</p>";

        $headers = new Header($raw_headers);
        $part = new Part($raw_body, $headers, 0);

        $this->assertSame('UTF-8', $part->charset);
        $this->assertSame('text/html', $part->content_type);
        $this->assertSame(39, $part->bytes);
        $this->assertSame(0, $part->part_number);
        $this->assertSame(false, $part->ifdisposition);
        $this->assertSame(false, $part->isAttachment());
        $this->assertSame("<p></p>\r\n<p dir=\"auto\">Any updates?</p>", $part->content);
        $this->assertSame(IMAP::MESSAGE_TYPE_TEXT, $part->type);
        $this->assertSame(IMAP::MESSAGE_ENC_7BIT, $part->encoding);
    }

    public function test_base64_part(): void
    {
        $raw_headers = "Content-Type: application/octet-stream; name=6mfFxiU5Yhv9WYJx.txt\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=6mfFxiU5Yhv9WYJx.txt\r\n";
        $raw_body = "em5rNTUxTVAzVFAzV1BwOUtsMWduTEVycldFZ2tKRkF0dmFLcWtUZ3JrM2RLSThkWDM4WVQ4QmFW\r\neFJjT0VSTg==";

        $headers = new Header($raw_headers);
        $part = new Part($raw_body, $headers, 0);

        $this->assertSame('', $part->charset);
        $this->assertSame('application/octet-stream', $part->content_type);
        $this->assertSame(90, $part->bytes);
        $this->assertSame(0, $part->part_number);
        $this->assertSame('znk551MP3TP3WPp9Kl1gnLErrWEgkJFAtvaKqkTgrk3dKI8dX38YT8BaVxRcOERN', base64_decode($part->content));
        $this->assertSame(true, $part->ifdisposition);
        $this->assertSame('attachment', $part->disposition);
        $this->assertSame('6mfFxiU5Yhv9WYJx.txt', $part->name);
        $this->assertSame('6mfFxiU5Yhv9WYJx.txt', $part->filename);
        $this->assertSame(true, $part->isAttachment());
        $this->assertSame(IMAP::MESSAGE_TYPE_TEXT, $part->type);
        $this->assertSame(IMAP::MESSAGE_ENC_BASE64, $part->encoding);
    }
}
