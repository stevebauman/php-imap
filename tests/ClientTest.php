<?php

namespace Tests;

use PHPUnit\Framework\MockObject\MockObject;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\ImapConnection;
use Webklex\PHPIMAP\Connection\Response;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Support\Masks\AttachmentMask;
use Webklex\PHPIMAP\Support\Masks\MessageMask;

class ClientTest extends TestCase
{
    protected Client $client;

    /** @var MockObject ImapProtocol mockup */
    protected MockObject $protocol;

    protected function setUp(): void
    {
        $manager = new ClientManager([
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

    public function test_client(): void
    {
        $this->createNewProtocolMockup();

        $this->assertInstanceOf(ImapConnection::class, $this->client->getConnection());
        $this->assertSame(true, $this->client->isConnected());
        $this->assertSame(false, $this->client->checkConnection());
        $this->assertSame(30, $this->client->getTimeout());
        $this->assertSame(MessageMask::class, $this->client->getDefaultMessageMask());
        $this->assertSame(AttachmentMask::class, $this->client->getDefaultAttachmentMask());
    }

    public function test_client_logout(): void
    {
        $this->createNewProtocolMockup();

        $this->protocol->expects($this->any())->method('logout')->willReturn(Response::empty()->setResponse([
            0 => "BYE Logging out\r\n",
            1 => "OK Logout completed (0.001 + 0.000 secs).\r\n",
        ]));
        $this->assertInstanceOf(Client::class, $this->client->disconnect());
    }

    public function test_client_expunge(): void
    {
        $this->createNewProtocolMockup();
        $this->protocol->expects($this->any())->method('expunge')->willReturn(Response::empty()->setResponse([
            0 => 'OK',
            1 => 'Expunge',
            2 => 'completed',
            3 => [
                0 => '0.001',
                1 => '+',
                2 => '0.000',
                3 => 'secs).',
            ],
        ]));
        $this->assertNotEmpty($this->client->expunge());
    }

    public function test_client_folders(): void
    {
        $this->createNewProtocolMockup();
        $this->protocol->expects($this->any())->method('expunge')->willReturn(Response::empty()->setResponse([
            0 => 'OK',
            1 => 'Expunge',
            2 => 'completed',
            3 => [
                0 => '0.001',
                1 => '+',
                2 => '0.000',
                3 => 'secs).',
            ],
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
        $this->assertNotEmpty($this->client->openFolder('INBOX'));
        $this->assertSame('INBOX', $this->client->getFolderPath());

        $this->protocol->expects($this->any())->method('examineFolder')->willReturn(Response::empty()->setResponse([
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
        $this->assertNotEmpty($this->client->checkFolder('INBOX'));

        $this->protocol->expects($this->any())->method('folders')->with($this->identicalTo(''), $this->identicalTo('*'))->willReturn(Response::empty()->setResponse([
            'INBOX' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasChildren",
                ],
            ],
            'INBOX.new' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                ],
            ],
            'INBOX.9AL56dEMTTgUKOAz' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                ],
            ],
            'INBOX.U9PsHCvXxAffYvie' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                ],
            ],
            'INBOX.Trash' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                    1 => "\Trash",
                ],
            ],
            'INBOX.processing' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                ],
            ],
            'INBOX.Sent' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                    1 => "\Sent",
                ],
            ],
            'INBOX.OzDWCXKV3t241koc' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                ],
            ],
            'INBOX.5F3bIVTtBcJEqIVe' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                ],
            ],
            'INBOX.8J3rll6eOBWnTxIU' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                ],
            ],
            'INBOX.Junk' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                    1 => "\Junk",
                ],
            ],
            'INBOX.Drafts' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                    1 => "\Drafts",
                ],
            ],
            'INBOX.test' => [
                'delimiter' => '.',
                'flags' => [
                    0 => "\HasNoChildren",
                ],
            ],
        ]));

        $this->protocol->expects($this->any())->method('createFolder')->willReturn(Response::empty()->setResponse([
            0 => "OK Create completed (0.004 + 0.000 + 0.003 secs).\r\n",
        ]));
        $this->assertNotEmpty($this->client->createFolder('INBOX.new'));

        $this->protocol->expects($this->any())->method('deleteFolder')->willReturn(Response::empty()->setResponse([
            0 => "OK Delete completed (0.007 + 0.000 + 0.006 secs).\r\n",
        ]));
        $this->assertNotEmpty($this->client->deleteFolder('INBOX.new'));

        $this->assertInstanceOf(Folder::class, $this->client->getFolderByPath('INBOX.new'));
        $this->assertInstanceOf(Folder::class, $this->client->getFolderByName('new'));
        $this->assertInstanceOf(Folder::class, $this->client->getFolder('INBOX.new', '.'));
        $this->assertInstanceOf(Folder::class, $this->client->getFolder('new'));
    }

    public function test_client_id(): void
    {
        $this->createNewProtocolMockup();
        $this->protocol->expects($this->any())->method('ID')->willReturn(Response::empty()->setResponse([
            0 => "ID (\"name\" \"Dovecot\")\r\n",
            1 => "OK ID completed (0.001 + 0.000 secs).\r\n",

        ]));
        $this->assertSame("ID (\"name\" \"Dovecot\")\r\n", $this->client->Id()[0]);
    }

    public function test_client_config(): void
    {
        $config = $this->client->getConfig();
        $this->assertSame('foo@domain.tld', $config['username']);
        $this->assertSame('bar', $config['password']);
        $this->assertSame('localhost', $config['host']);
        $this->assertSame(true, $config['validate_cert']);
        $this->assertSame(993, $config['port']);

        $this->client->setConfig([
            'host' => 'domain.tld',
            'password' => 'bar',
        ]);
        $config = $this->client->getConfig();
        $this->assertSame('bar', $config['password']);
        $this->assertSame('domain.tld', $config['host']);
        $this->assertSame(true, $config['validate_cert']);
    }

    protected function createNewProtocolMockup(): void
    {
        $this->protocol = $this->createMock(ImapConnection::class);

        $this->protocol->expects($this->any())->method('connected')->willReturn(true);
        $this->protocol->expects($this->any())->method('getConnectionTimeout')->willReturn(30);

        $this->client->connection = $this->protocol;
    }
}
