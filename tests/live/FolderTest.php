<?php

/*
* File: FolderTest.php
* Category: -
* Author: M.Goldenbaum
* Created: 04.03.23 03:52
* Updated: -
*
* Description:
*  -
*/

namespace Tests\live;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\EventNotFoundException;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\InvalidMessageDateException;
use Webklex\PHPIMAP\Exceptions\MaskNotFoundException;
use Webklex\PHPIMAP\Exceptions\MessageContentFetchingException;
use Webklex\PHPIMAP\Exceptions\MessageFlagException;
use Webklex\PHPIMAP\Exceptions\MessageHeaderFetchingException;
use Webklex\PHPIMAP\Exceptions\MessageNotFoundException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\FolderCollection;

/**
 * Class FolderTest.
 */
class FolderTest extends LiveMailboxTestCase
{
    /**
     * Try to create a new query instance.
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_query(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        self::assertInstanceOf(WhereQuery::class, $folder->query());
        self::assertInstanceOf(WhereQuery::class, $folder->search());
        self::assertInstanceOf(WhereQuery::class, $folder->messages());
    }

    /**
     * Test Folder::hasChildren().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     * @throws EventNotFoundException
     */
    public function test_has_children(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $delimiter = $this->getManager()->get('options.delimiter');
        $child_path = implode($delimiter, ['INBOX', 'test']);
        if ($folder->getClient()->getFolder($child_path) === null) {
            $folder->getClient()->createFolder($child_path, false);
            $folder = $this->getFolder('INBOX');
        }

        self::assertTrue($folder->hasChildren());
    }

    /**
     * Test Folder::setChildren().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_set_children(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $delimiter = $this->getManager()->get('options.delimiter');
        $child_path = implode($delimiter, ['INBOX', 'test']);
        if ($folder->getClient()->getFolder($child_path) === null) {
            $folder->getClient()->createFolder($child_path, false);
            $folder = $this->getFolder('INBOX');
        }
        self::assertTrue($folder->hasChildren());

        $folder->setChildren(new FolderCollection);
        self::assertTrue($folder->getChildren()->isEmpty());
    }

    /**
     * Test Folder::getChildren().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_get_children(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $delimiter = $this->getManager()->get('options.delimiter');
        $child_path = implode($delimiter, ['INBOX', 'test']);
        if ($folder->getClient()->getFolder($child_path) === null) {
            $folder->getClient()->createFolder($child_path, false);
        }

        $folder = $folder->getClient()->getFolders()->where('name', 'INBOX')->first();
        self::assertInstanceOf(Folder::class, $folder);

        self::assertTrue($folder->hasChildren());
        self::assertFalse($folder->getChildren()->isEmpty());
    }

    /**
     * Test Folder::move().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_move(): void
    {
        $client = $this->getClient();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'test']);

        $folder = $client->getFolder($folder_path);
        if ($folder === null) {
            $folder = $client->createFolder($folder_path, false);
        }
        $new_folder_path = implode($delimiter, ['INBOX', 'other']);
        $new_folder = $client->getFolder($new_folder_path);
        $new_folder?->delete(false);

        $status = $folder->move($new_folder_path, false);
        self::assertIsArray($status);
        self::assertTrue(str_starts_with($status[0], 'OK'));

        $new_folder = $client->getFolder($new_folder_path);
        self::assertEquals($new_folder_path, $new_folder->path);
        self::assertEquals('other', $new_folder->name);

        if ($this->deleteFolder($new_folder) === false) {
            $this->fail('Could not delete folder: '.$new_folder->path);
        }
    }

    /**
     * Test Folder::delete().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_delete(): void
    {
        $client = $this->getClient();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'test']);

        $folder = $client->getFolder($folder_path);
        if ($folder === null) {
            $folder = $client->createFolder($folder_path, false);
        }
        self::assertInstanceOf(Folder::class, $folder);

        if ($this->deleteFolder($folder) === false) {
            $this->fail('Could not delete folder: '.$folder->path);
        }
    }

    /**
     * Test Folder::overview().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     * @throws InvalidMessageDateException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     */
    public function test_overview(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $folder->select();

        // Test empty overview
        $overview = $folder->overview();
        self::assertIsArray($overview);
        self::assertCount(0, $overview);

        $message = $this->appendMessageTemplate($folder, 'plain.eml');

        $overview = $folder->overview();

        self::assertIsArray($overview);
        self::assertCount(1, $overview);

        self::assertEquals($message->from->first()->full, end($overview)['from']->toString());

        self::assertTrue($message->delete());
    }

    /**
     * Test Folder::appendMessage().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_append_message(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $message = $this->appendMessageTemplate($folder, 'plain.eml');
        self::assertInstanceOf(Message::class, $message);

        self::assertEquals('Example', $message->subject);
        self::assertEquals('to@someone-else.com', $message->to);
        self::assertEquals('from@someone.com', $message->from);

        // Clean up
        $this->assertTrue($message->delete(true));
    }

    /**
     * Test Folder::subscribe().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_subscribe(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $status = $folder->subscribe();
        self::assertIsArray($status);
        self::assertTrue(str_starts_with($status[0], 'OK'));

        // Clean up
        $folder->unsubscribe();
    }

    /**
     * Test Folder::unsubscribe().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_unsubscribe(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $folder->subscribe();

        $status = $folder->subscribe();
        self::assertIsArray($status);
        self::assertTrue(str_starts_with($status[0], 'OK'));
    }

    /**
     * Test Folder::status().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_status(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $status = $folder->status();
        self::assertEquals(0, $status['messages']);
        self::assertEquals(0, $status['recent']);
        self::assertEquals(0, $status['unseen']);
        self::assertGreaterThan(0, $status['uidnext']);
        self::assertGreaterThan(0, $status['uidvalidity']);
    }

    /**
     * Test Folder::examine().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_examine(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $status = $folder->examine();
        self::assertTrue(isset($status['flags']) && count($status['flags']) > 0);
        self::assertTrue(($status['uidnext'] ?? 0) > 0);
        self::assertTrue(($status['uidvalidity'] ?? 0) > 0);
        self::assertTrue(($status['recent'] ?? -1) >= 0);
        self::assertTrue(($status['exists'] ?? -1) >= 0);
    }

    /**
     * Test Folder::getClient().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_get_client(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);
        self::assertInstanceOf(Client::class, $folder->getClient());
    }

    /**
     * Test Folder::setDelimiter().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws MaskNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test_set_delimiter(): void
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $folder->setDelimiter('/');
        self::assertEquals('/', $folder->delimiter);

        $folder->setDelimiter('.');
        self::assertEquals('.', $folder->delimiter);

        $default_delimiter = $this->getManager()->get('options.delimiter', '/');
        $folder->setDelimiter(null);
        self::assertEquals($default_delimiter, $folder->delimiter);
    }
}
