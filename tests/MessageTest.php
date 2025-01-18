<?php

namespace Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Connection\Protocols\Response;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

class MessageTest extends TestCase
{
    protected Message $message;

    protected Client $client;

    /** @var MockObject ImapProtocol mockup */
    protected MockObject $protocol;

    protected function setUp(): void
    {
        $this->client = new Client([
            'protocol' => 'imap',
            'encryption' => 'ssl',
            'username' => 'foo@domain.tld',
            'password' => 'bar',
            'proxy' => [
                'socket' => null,
                'request_fulluri' => false,
                'username' => null,
                'password' => null,
            ],
        ]);
    }

    public function test_message(): void
    {
        $this->createNewProtocolMockup();

        $email = file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, 'messages', '1366671050@github.com.eml']));
        if (! str_contains($email, "\r\n")) {
            $email = str_replace("\n", "\r\n", $email);
        }

        $raw_header = substr($email, 0, strpos($email, "\r\n\r\n"));
        $raw_body = substr($email, strlen($raw_header) + 8);

        $this->protocol->expects($this->any())->method('getUid')->willReturn(Response::empty()->setResult(22));
        $this->protocol->expects($this->any())->method('getMessageNumber')->willReturn(Response::empty()->setResult(21));
        $this->protocol->expects($this->any())->method('flags')->willReturn(Response::empty()->setResult([22 => [0 => '\\Seen']]));

        self::assertNotEmpty($this->client->openFolder('INBOX'));

        $message = Message::make(22, null, $this->client, $raw_header, $raw_body, [0 => '\\Seen'], IMAP::ST_UID);

        self::assertInstanceOf(Client::class, $message->getClient());
        self::assertSame(22, $message->uid);
        self::assertSame(21, $message->msgn);
        self::assertContains('Seen', $message->flags()->toArray());

        $subject = $message->get('subject');
        $returnPath = $message->get('Return-Path');

        self::assertInstanceOf(Attribute::class, $subject);
        self::assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', $subject->toString());
        self::assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', (string) $message->subject);
        self::assertSame('<noreply@github.com>', $returnPath->toString());
        self::assertSame('return_path', $returnPath->getName());
        self::assertSame('-4.299', (string) $message->get('X-Spam-Score'));
        self::assertSame('Webklex/php-imap/issues/349/1365266070@github.com', (string) $message->get('Message-ID'));
        self::assertSame(6, $message->get('received')->count());
        self::assertSame(IMAP::MESSAGE_PRIORITY_UNKNOWN, (int) $message->get('priority')());
    }

    public function test_get_message_number(): void
    {
        $this->createNewProtocolMockup();
        $this->protocol->expects($this->any())->method('getMessageNumber')->willReturn(Response::empty()->setResult(''));

        self::assertNotEmpty($this->client->openFolder('INBOX'));

        try {
            $this->client->getConnection()->getMessageNumber(21)->validatedData();
            $this->fail('Message number should not exist');
        } catch (ResponseException $e) {
            self::assertTrue(true);
        }
    }

    public function test_load_message_from_file(): void
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, 'messages', '1366671050@github.com.eml']);
        $message = Message::fromFile($filename);

        $subject = $message->get('subject');
        $returnPath = $message->get('Return-Path');

        self::assertInstanceOf(Attribute::class, $subject);
        self::assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', $subject->toString());
        self::assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', (string) $message->subject);
        self::assertSame('<noreply@github.com>', $returnPath->toString());
        self::assertSame('return_path', $returnPath->getName());
        self::assertSame('-4.299', (string) $message->get('X-Spam-Score'));
        self::assertSame('Webklex/php-imap/issues/349/1365266070@github.com', (string) $message->get('Message-ID'));
        self::assertSame(6, $message->get('received')->count());
        self::assertSame(IMAP::MESSAGE_PRIORITY_UNKNOWN, (int) $message->get('priority')());

        self::assertNull($message->getClient());
        self::assertSame(0, $message->uid);

        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, 'messages', 'example_attachment.eml']);
        $message = Message::fromFile($filename);

        $subject = $message->get('subject');
        $returnPath = $message->get('Return-Path');

        self::assertInstanceOf(Attribute::class, $subject);
        self::assertSame('ogqMVHhz7swLaq2PfSWsZj0k99w8wtMbrb4RuHdNg53i76B7icIIM0zIWpwGFtnk', $subject->toString());
        self::assertSame('ogqMVHhz7swLaq2PfSWsZj0k99w8wtMbrb4RuHdNg53i76B7icIIM0zIWpwGFtnk', (string) $message->subject);
        self::assertSame('<someone@domain.tld>', $returnPath->toString());
        self::assertSame('return_path', $returnPath->getName());
        self::assertSame('1.103', (string) $message->get('X-Spam-Score'));
        self::assertSame('d3a5e91963cb805cee975687d5acb1c6@swift.generated', (string) $message->get('Message-ID'));
        self::assertSame(5, $message->get('received')->count());
        self::assertSame(IMAP::MESSAGE_PRIORITY_HIGHEST, (int) $message->get('priority')());

        self::assertNull($message->getClient());
        self::assertSame(0, $message->uid);
        self::assertSame(1, $message->getAttachments()->count());

        /** @var Attachment $attachment */
        $attachment = $message->getAttachments()->first();
        self::assertSame('attachment', $attachment->disposition);
        self::assertSame('znk551MP3TP3WPp9Kl1gnLErrWEgkJFAtvaKqkTgrk3dKI8dX38YT8BaVxRcOERN', $attachment->content);
        self::assertSame('application/octet-stream', $attachment->content_type);
        self::assertSame('6mfFxiU5Yhv9WYJx.txt', $attachment->name);
        self::assertSame(2, $attachment->part_number);
        self::assertSame('text', $attachment->type);
        self::assertNotEmpty($attachment->id);
        self::assertSame(90, $attachment->size);
        self::assertSame('txt', $attachment->getExtension());
        self::assertInstanceOf(Message::class, $attachment->getMessage());
        self::assertSame('text/plain', $attachment->getMimeType());
    }

    public function test_issue348()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, 'messages', 'issue-348.eml']);
        $message = Message::fromFile($filename);

        self::assertSame(1, $message->getAttachments()->count());

        /** @var Attachment $attachment */
        $attachment = $message->getAttachments()->first();

        self::assertSame('attachment', $attachment->disposition);
        self::assertSame('application/pdf', $attachment->content_type);
        self::assertSame('Kelvinsong—Font_test_page_bold.pdf', $attachment->name);
        self::assertSame(1, $attachment->part_number);
        self::assertSame('text', $attachment->type);
        self::assertNotEmpty($attachment->id);
        self::assertSame(92384, $attachment->size);
        self::assertSame('pdf', $attachment->getExtension());
        self::assertInstanceOf(Message::class, $attachment->getMessage());
        self::assertSame('application/pdf', $attachment->getMimeType());
    }

    protected function createNewProtocolMockup(): void
    {
        $this->protocol = $this->createMock(ImapProtocol::class);

        $this->protocol->expects($this->any())->method('createStream')->willReturn(true);
        $this->protocol->expects($this->any())->method('connected')->willReturn(true);
        $this->protocol->expects($this->any())->method('getConnectionTimeout')->willReturn(30);
        $this->protocol->expects($this->any())->method('logout')->willReturn(Response::empty()->setResponse([
            0 => "BYE Logging out\r\n",
            1 => "OK Logout completed (0.001 + 0.000 secs).\r\n",
        ]));
        $this->protocol->expects($this->any())->method('selectFolder')->willReturn(Response::empty()->setResponse([
            'flags' => [
                0 => [
                    0 => "\Answered",
                    1 => "\Flagged",
                    2 => "\Deleted",
                    3 => "\Seen",
                    4 => "\Draft",
                    5 => 'NonJunk',
                    6 => 'unknown-1',
                ],
            ],
            'exists' => 139,
            'recent' => 0,
            'unseen' => 94,
            'uidvalidity' => 1488899637,
            'uidnext' => 278,
        ]));

        $this->client->connection = $this->protocol;
    }
}
