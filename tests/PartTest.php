<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
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

        self::assertSame('UTF-8', $part->charset);
        self::assertSame('text/plain', $part->content_type);
        self::assertSame(12, $part->bytes);
        self::assertSame(0, $part->part_number);
        self::assertSame(false, $part->ifdisposition);
        self::assertSame(false, $part->isAttachment());
        self::assertSame('Any updates?', $part->content);
        self::assertSame(IMAP::MESSAGE_TYPE_TEXT, $part->type);
        self::assertSame(IMAP::MESSAGE_ENC_7BIT, $part->encoding);
    }

    public function test_html_part(): void
    {
        $raw_headers = "Content-Type: text/html;\r\n charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n";
        $raw_body = "\r\n<p></p>\r\n<p dir=\"auto\">Any updates?</p>";

        $headers = new Header($raw_headers);
        $part = new Part($raw_body, $headers, 0);

        self::assertSame('UTF-8', $part->charset);
        self::assertSame('text/html', $part->content_type);
        self::assertSame(39, $part->bytes);
        self::assertSame(0, $part->part_number);
        self::assertSame(false, $part->ifdisposition);
        self::assertSame(false, $part->isAttachment());
        self::assertSame("<p></p>\r\n<p dir=\"auto\">Any updates?</p>", $part->content);
        self::assertSame(IMAP::MESSAGE_TYPE_TEXT, $part->type);
        self::assertSame(IMAP::MESSAGE_ENC_7BIT, $part->encoding);
    }

    public function test_base64_part(): void
    {
        $raw_headers = "Content-Type: application/octet-stream; name=6mfFxiU5Yhv9WYJx.txt\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=6mfFxiU5Yhv9WYJx.txt\r\n";
        $raw_body = "em5rNTUxTVAzVFAzV1BwOUtsMWduTEVycldFZ2tKRkF0dmFLcWtUZ3JrM2RLSThkWDM4WVQ4QmFW\r\neFJjT0VSTg==";

        $headers = new Header($raw_headers);
        $part = new Part($raw_body, $headers, 0);

        self::assertSame('', $part->charset);
        self::assertSame('application/octet-stream', $part->content_type);
        self::assertSame(90, $part->bytes);
        self::assertSame(0, $part->part_number);
        self::assertSame('znk551MP3TP3WPp9Kl1gnLErrWEgkJFAtvaKqkTgrk3dKI8dX38YT8BaVxRcOERN', base64_decode($part->content));
        self::assertSame(true, $part->ifdisposition);
        self::assertSame('attachment', $part->disposition);
        self::assertSame('6mfFxiU5Yhv9WYJx.txt', $part->name);
        self::assertSame('6mfFxiU5Yhv9WYJx.txt', $part->filename);
        self::assertSame(true, $part->isAttachment());
        self::assertSame(IMAP::MESSAGE_TYPE_TEXT, $part->type);
        self::assertSame(IMAP::MESSAGE_ENC_BASE64, $part->encoding);
    }
}
