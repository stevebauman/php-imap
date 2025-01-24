<?php

namespace Tests\live;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Connection\ConnectionInterface;
use Webklex\PHPIMAP\EncodingAliases;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Support\Masks\AttachmentMask;
use Webklex\PHPIMAP\Support\Masks\MessageMask;

class ClientTest extends LiveMailboxTestCase
{
    public function test_connect(): void
    {
        $this->assertNotNull($this->getClient()->connect());
    }

    public function test_is_connected(): void
    {
        $client = $this->getClient()->connect();

        $this->assertTrue($client->isConnected());
    }

    public function test_disconnect(): void
    {
        $client = $this->getClient()->connect();

        $this->assertFalse($client->disconnect()->isConnected());
    }

    public function test_get_folder(): void
    {
        $client = $this->getClient()->connect();

        $folder = $client->getFolder('INBOX');
        $this->assertInstanceOf(Folder::class, $folder);
    }

    public function test_get_folder_by_name(): void
    {
        $client = $this->getClient()->connect();

        $folder = $client->getFolderByName('INBOX');
        $this->assertInstanceOf(Folder::class, $folder);
    }

    public function test_get_folder_by_path(): void
    {
        $client = $this->getClient()->connect();

        $folder = $client->getFolderByPath('INBOX');
        $this->assertInstanceOf(Folder::class, $folder);
    }

    public function test_get_folders(): void
    {
        $client = $this->getClient()->connect();

        $folders = $client->getFolders(false);
        $this->assertTrue($folders->count() > 0);
    }

    public function test_get_folders_with_status(): void
    {
        $client = $this->getClient()->connect();

        $folders = $client->getFoldersWithStatus(false);
        $this->assertTrue($folders->count() > 0);
    }

    public function test_open_folder(): void
    {
        $client = $this->getClient()->connect();

        $status = $client->openFolder('INBOX');
        $this->assertTrue(isset($status['flags']) && count($status['flags']) > 0);
        $this->assertTrue(($status['uidnext'] ?? 0) > 0);
        $this->assertTrue(($status['uidvalidity'] ?? 0) > 0);
        $this->assertTrue(($status['recent'] ?? -1) >= 0);
        $this->assertTrue(($status['exists'] ?? -1) >= 0);
    }

    public function test_create_folder(): void
    {
        $client = $this->getClient()->connect();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', $this->getSpecialChars()]);

        $folder = $client->getFolder($folder_path);

        $this->deleteFolder($folder);

        $folder = $client->createFolder($folder_path, false);
        $this->assertInstanceOf(Folder::class, $folder);

        $folder = $this->getFolder($folder_path);
        $this->assertInstanceOf(Folder::class, $folder);

        $this->assertEquals($this->getSpecialChars(), $folder->name);
        $this->assertEquals($folder_path, $folder->fullName);

        $folder_path = implode($delimiter, ['INBOX', EncodingAliases::convert($this->getSpecialChars(), 'utf-8', 'utf7-imap')]);
        $this->assertEquals($folder_path, $folder->path);

        // Clean up
        if ($this->deleteFolder($folder) === false) {
            $this->fail('Could not delete folder: '.$folder->path);
        }
    }

    public function test_check_folder(): void
    {
        $client = $this->getClient()->connect();

        $status = $client->checkFolder('INBOX');
        $this->assertTrue(isset($status['flags']) && count($status['flags']) > 0);
        $this->assertTrue(($status['uidnext'] ?? 0) > 0);
        $this->assertTrue(($status['uidvalidity'] ?? 0) > 0);
        $this->assertTrue(($status['recent'] ?? -1) >= 0);
        $this->assertTrue(($status['exists'] ?? -1) >= 0);
    }

    public function test_get_folder_path(): void
    {
        $client = $this->getClient()->connect();

        $this->assertIsArray($client->openFolder('INBOX'));
        $this->assertEquals('INBOX', $client->getFolderPath());
    }

    public function test_id(): void
    {
        $client = $this->getClient()->connect();

        $info = $client->Id();
        $this->assertIsArray($info);
        $valid = false;
        foreach ($info as $value) {
            if (str_starts_with($value, 'OK')) {
                $valid = true;
                break;
            }
        }
        $this->assertTrue($valid);
    }

    public function test_get_quota_root(): void
    {
        if (! getenv('LIVE_MAILBOX_QUOTA_SUPPORT')) {
            $this->markTestSkipped('Quota support is not enabled');
        }

        $client = $this->getClient()->connect();

        $quota = $client->getQuotaRoot();
        $this->assertIsArray($quota);
        $this->assertTrue(count($quota) > 1);
        $this->assertIsArray($quota[0]);
        $this->assertEquals('INBOX', $quota[0][1]);
        $this->assertIsArray($quota[1]);
        $this->assertIsArray($quota[1][2]);
        $this->assertTrue($quota[1][2][2] > 0);
    }

    public function test_set_timeout(): void
    {
        $client = $this->getClient()->connect();

        $this->assertInstanceOf(ConnectionInterface::class, $client->setTimeout(57));
        $this->assertEquals(57, $client->getTimeout());
    }

    public function test_expunge(): void
    {
        $client = $this->getClient()->connect();

        $client->openFolder('INBOX');
        $status = $client->expunge();

        $this->assertIsArray($status);
        $this->assertIsArray($status[0]);
        $this->assertEquals('OK', $status[0][0]);
    }

    public function test_get_default_message_mask(): void
    {
        $client = $this->getClient();

        $this->assertEquals(MessageMask::class, $client->getDefaultMessageMask());
    }

    public function test_get_default_events(): void
    {
        $client = $this->getClient();

        $this->assertIsArray($client->getDefaultEvents('message'));
    }

    public function test_set_default_message_mask(): void
    {
        $client = $this->getClient();

        $this->assertInstanceOf(Client::class, $client->setDefaultMessageMask(AttachmentMask::class));
        $this->assertEquals(AttachmentMask::class, $client->getDefaultMessageMask());

        $client->setDefaultMessageMask(MessageMask::class);
    }

    public function test_get_default_attachment_mask(): void
    {
        $client = $this->getClient();

        $this->assertEquals(AttachmentMask::class, $client->getDefaultAttachmentMask());
    }

    public function test_set_default_attachment_mask(): void
    {
        $client = $this->getClient();

        $this->assertInstanceOf(Client::class, $client->setDefaultAttachmentMask(MessageMask::class));
        $this->assertEquals(MessageMask::class, $client->getDefaultAttachmentMask());

        $client->setDefaultAttachmentMask(AttachmentMask::class);
    }
}
