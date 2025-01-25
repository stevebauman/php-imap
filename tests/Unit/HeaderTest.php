<?php

namespace Tests\Unit;

use Carbon\Carbon;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Tests\TestCase;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\Imap;

class HeaderTest extends TestCase
{
    public function test_header_parsing(): void
    {
        $email = file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', '1366671050@github.com.eml']));

        if (! str_contains($email, "\r\n")) {
            $email = str_replace("\n", "\r\n", $email);
        }

        $rawHeader = substr($email, 0, strpos($email, "\r\n\r\n"));

        $header = new Header($rawHeader);

        $subject = $header->get('subject');
        $returnPath = $header->get('Return-Path');

        /** @var Carbon $date */
        $date = $header->get('date')->first();
        /** @var Address $from */
        $from = $header->get('from')->first();
        /** @var Address $to */
        $to = $header->get('to')->first();

        $this->assertSame($rawHeader, $header->raw);
        $this->assertSame($returnPath, $header->return_path);
        $this->assertInstanceOf(Attribute::class, $subject);
        $this->assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', $subject->toString());
        $this->assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', (string) $header->subject);
        $this->assertSame('<noreply@github.com>', $returnPath->toString());
        $this->assertSame('return_path', $returnPath->getName());
        $this->assertSame('-4.299', (string) $header->get('X-Spam-Score'));
        $this->assertSame('Webklex/php-imap/issues/349/1365266070@github.com', (string) $header->get('Message-ID'));
        $this->assertSame(6, $header->get('received')->count());
        $this->assertSame(Imap::MESSAGE_PRIORITY_UNKNOWN, (int) $header->get('priority')());

        $this->assertSame('Username', $from->personal);
        $this->assertSame('notifications', $from->mailbox);
        $this->assertSame('github.com', $from->host);
        $this->assertSame('notifications@github.com', $from->mail);
        $this->assertSame('Username <notifications@github.com>', $from->full);

        $this->assertSame('Webklex/php-imap', $to->personal);
        $this->assertSame('php-imap', $to->mailbox);
        $this->assertSame('noreply.github.com', $to->host);
        $this->assertSame('php-imap@noreply.github.com', $to->mail);
        $this->assertSame('Webklex/php-imap <php-imap@noreply.github.com>', $to->full);

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertSame('2022-12-26 08:07:14 GMT-0800', $date->format('Y-m-d H:i:s T'));

        $this->assertSame(48, count($header->getAttributes()));
    }

    public function test_rfc822_parse_headers()
    {
        $mock = $this->getMockBuilder(Header::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $config = new ReflectionProperty($mock, 'config');
        $config->setAccessible(true);
        $config->setValue($mock, ['rfc822' => true]);

        $mockHeader = "Content-Type: text/csv; charset=WINDOWS-1252;  name*0=\"TH_Is_a_F ile name example 20221013.c\"; name*1=sv\r\nContent-Transfer-Encoding: quoted-printable\r\nContent-Disposition: attachment; filename*0=\"TH_Is_a_F ile name example 20221013.c\"; filename*1=\"sv\"\r\n";

        $expected = new stdClass;
        $expected->content_type = 'text/csv; charset=WINDOWS-1252;  name*0="TH_Is_a_F ile name example 20221013.c"; name*1=sv';
        $expected->content_transfer_encoding = 'quoted-printable';
        $expected->content_disposition = 'attachment; filename*0="TH_Is_a_F ile name example 20221013.c"; filename*1="sv"';

        $this->assertEquals($expected, $mock->rfc822ParseHeaders($mockHeader));
    }

    public function test_extract_header_extensions()
    {
        $mock = $this->getMockBuilder(Header::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $method = new ReflectionMethod($mock, 'extractHeaderExtensions');
        $method->setAccessible(true);

        $mockAttributes = [
            'content_type' => new Attribute('content_type', 'text/csv; charset=WINDOWS-1252;  name*0="TH_Is_a_F ile name example 20221013.c"; name*1=sv'),
            'content_transfer_encoding' => new Attribute('content_transfer_encoding', 'quoted-printable'),
            'content_disposition' => new Attribute('content_disposition', 'attachment; filename*0="TH_Is_a_F ile name example 20221013.c"; filename*1="sv"; attribute_test=attribute_test_value'),
        ];

        $attributes = new ReflectionProperty($mock, 'attributes');
        $attributes->setAccessible(true);
        $attributes->setValue($mock, $mockAttributes);

        $method->invoke($mock);

        $this->assertArrayHasKey('filename', $mock->getAttributes());
        $this->assertArrayNotHasKey('filename*0', $mock->getAttributes());
        $this->assertEquals('TH_Is_a_F ile name example 20221013.csv', $mock->get('filename'));

        $this->assertArrayHasKey('name', $mock->getAttributes());
        $this->assertArrayNotHasKey('name*0', $mock->getAttributes());
        $this->assertEquals('TH_Is_a_F ile name example 20221013.csv', $mock->get('name'));

        $this->assertArrayHasKey('content_type', $mock->getAttributes());
        $this->assertEquals('text/csv', $mock->get('content_type')->last());

        $this->assertArrayHasKey('charset', $mock->getAttributes());
        $this->assertEquals('WINDOWS-1252', $mock->get('charset')->last());

        $this->assertArrayHasKey('content_transfer_encoding', $mock->getAttributes());
        $this->assertEquals('quoted-printable', $mock->get('content_transfer_encoding'));

        $this->assertArrayHasKey('content_disposition', $mock->getAttributes());
        $this->assertEquals('attachment', $mock->get('content_disposition')->last());
        $this->assertEquals('quoted-printable', $mock->get('content_transfer_encoding'));

        $this->assertArrayHasKey('attribute_test', $mock->getAttributes());
        $this->assertEquals('attribute_test_value', $mock->get('attribute_test'));
    }
}
