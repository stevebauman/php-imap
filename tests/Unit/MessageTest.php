<?php

namespace Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientContainer;
use Webklex\PHPIMAP\Connection\ImapConnection;
use Webklex\PHPIMAP\Connection\Response;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Imap;
use Webklex\PHPIMAP\Message;

class MessageTest extends TestCase
{
    protected Message $message;

    protected Client $client;

    /** @var MockObject ImapProtocol mockup */
    protected MockObject $protocol;

    protected function setUp(): void
    {
        $manager = ClientContainer::getNewInstance([
            'accounts' => [
                'default' => [
                    'encryption' => 'ssl',
                    'username' => 'foo@domain.tld',
                    'password' => 'bar',
                    'proxy' => [
                        'socket' => null,
                        'request_fulluri' => false,
                        'username' => null,
                        'password' => null,
                    ],
                ],
            ],
        ]);

        $this->client = $manager->account('default');
    }

    public function test_message(): void
    {
        $this->createNewProtocolMockup();

        $email = file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', '1366671050@github.com.eml']));
        if (! str_contains($email, "\r\n")) {
            $email = str_replace("\n", "\r\n", $email);
        }

        $raw_header = substr($email, 0, strpos($email, "\r\n\r\n"));
        $raw_body = substr($email, strlen($raw_header) + 8);

        $this->protocol->expects($this->any())->method('getUid')->willReturn(Response::empty()->setResult(22));
        $this->protocol->expects($this->any())->method('getMessageNumber')->willReturn(Response::empty()->setResult(21));
        $this->protocol->expects($this->any())->method('flags')->willReturn(Response::empty()->setResult([22 => [0 => '\\Seen']]));

        $this->assertNotEmpty($this->client->openFolder('INBOX'));

        $message = Message::make(22, null, $this->client, $raw_header, $raw_body, [0 => '\\Seen'], Imap::ST_UID);

        $this->assertInstanceOf(Client::class, $message->getClient());
        $this->assertSame(22, $message->uid);
        $this->assertSame(21, $message->msgn);
        $this->assertContains('Seen', $message->flags()->toArray());

        $subject = $message->get('subject');
        $returnPath = $message->get('Return-Path');

        $this->assertInstanceOf(Attribute::class, $subject);
        $this->assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', $subject->toString());
        $this->assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', (string) $message->subject);
        $this->assertSame('<noreply@github.com>', $returnPath->toString());
        $this->assertSame('return_path', $returnPath->getName());
        $this->assertSame('-4.299', (string) $message->get('X-Spam-Score'));
        $this->assertSame('Webklex/php-imap/issues/349/1365266070@github.com', (string) $message->get('Message-ID'));
        $this->assertSame(6, $message->get('received')->count());
        $this->assertSame(Imap::MESSAGE_PRIORITY_UNKNOWN, (int) $message->get('priority')());
    }

    public function test_get_message_number(): void
    {
        $this->createNewProtocolMockup();
        $this->protocol->expects($this->any())->method('getMessageNumber')->willReturn(Response::empty()->setResult(''));

        $this->assertNotEmpty($this->client->openFolder('INBOX'));

        try {
            $this->client->getConnection()->getMessageNumber(21)->getValidatedData();
            $this->fail('Message number should not exist');
        } catch (ResponseException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_load_message_from_file(): void
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', '1366671050@github.com.eml']);
        $message = Message::fromFile($filename);

        $subject = $message->get('subject');
        $returnPath = $message->get('Return-Path');

        $this->assertInstanceOf(Attribute::class, $subject);
        $this->assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', $subject->toString());
        $this->assertSame('Re: [Webklex/php-imap] Read all folders? (Issue #349)', (string) $message->subject);
        $this->assertSame('<noreply@github.com>', $returnPath->toString());
        $this->assertSame('return_path', $returnPath->getName());
        $this->assertSame('-4.299', (string) $message->get('X-Spam-Score'));
        $this->assertSame('Webklex/php-imap/issues/349/1365266070@github.com', (string) $message->get('Message-ID'));
        $this->assertSame(6, $message->get('received')->count());
        $this->assertSame(Imap::MESSAGE_PRIORITY_UNKNOWN, (int) $message->get('priority')());

        $this->assertNull($message->getClient());
        $this->assertSame(0, $message->uid);

        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'example_attachment.eml']);
        $message = Message::fromFile($filename);

        $subject = $message->get('subject');
        $returnPath = $message->get('Return-Path');

        $this->assertInstanceOf(Attribute::class, $subject);
        $this->assertSame('ogqMVHhz7swLaq2PfSWsZj0k99w8wtMbrb4RuHdNg53i76B7icIIM0zIWpwGFtnk', $subject->toString());
        $this->assertSame('ogqMVHhz7swLaq2PfSWsZj0k99w8wtMbrb4RuHdNg53i76B7icIIM0zIWpwGFtnk', (string) $message->subject);
        $this->assertSame('<someone@domain.tld>', $returnPath->toString());
        $this->assertSame('return_path', $returnPath->getName());
        $this->assertSame('1.103', (string) $message->get('X-Spam-Score'));
        $this->assertSame('d3a5e91963cb805cee975687d5acb1c6@swift.generated', (string) $message->get('Message-ID'));
        $this->assertSame(5, $message->get('received')->count());
        $this->assertSame(Imap::MESSAGE_PRIORITY_HIGHEST, (int) $message->get('priority')());

        $this->assertNull($message->getClient());
        $this->assertSame(0, $message->uid);
        $this->assertSame(1, $message->getAttachments()->count());

        /** @var Attachment $attachment */
        $attachment = $message->getAttachments()->first();
        $this->assertSame('attachment', $attachment->disposition);
        $this->assertSame('znk551MP3TP3WPp9Kl1gnLErrWEgkJFAtvaKqkTgrk3dKI8dX38YT8BaVxRcOERN', $attachment->content);
        $this->assertSame('application/octet-stream', $attachment->content_type);
        $this->assertSame('6mfFxiU5Yhv9WYJx.txt', $attachment->name);
        $this->assertSame(2, $attachment->part_number);
        $this->assertSame('text', $attachment->type);
        $this->assertNotEmpty($attachment->id);
        $this->assertSame(90, $attachment->size);
        $this->assertSame('txt', $attachment->getExtension());
        $this->assertInstanceOf(Message::class, $attachment->getMessage());
        $this->assertSame('text/plain', $attachment->getMimeType());
    }

    public function test_issue348()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-348.eml']);
        $message = Message::fromFile($filename);

        $this->assertSame(1, $message->getAttachments()->count());

        /** @var Attachment $attachment */
        $attachment = $message->getAttachments()->first();

        $this->assertSame('attachment', $attachment->disposition);
        $this->assertSame('application/pdf', $attachment->content_type);
        $this->assertSame('Kelvinsong—Font_test_page_bold.pdf', $attachment->name);
        $this->assertSame(1, $attachment->part_number);
        $this->assertSame('text', $attachment->type);
        $this->assertNotEmpty($attachment->id);
        $this->assertSame(92384, $attachment->size);
        $this->assertSame('pdf', $attachment->getExtension());
        $this->assertInstanceOf(Message::class, $attachment->getMessage());
        $this->assertSame('application/pdf', $attachment->getMimeType());
    }

    protected function createNewProtocolMockup(): void
    {
        $this->protocol = $this->createMock(ImapConnection::class);

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
