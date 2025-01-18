<?php

/*
* File: LiveMailboxTestCase.php
* Category: -
* Author: M.Goldenbaum
* Created: 04.03.23 03:43
* Updated: -
*
* Description:
*  -
*/

namespace Tests\live;

use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

abstract class LiveMailboxTestCase extends TestCase
{
    const SPECIAL_CHARS = 'A_\\|!"£$%&()=?àèìòùÀÈÌÒÙ<>-@#[]_ß_б_π_€_✔_你_يد_Z_';

    protected static ClientManager $manager;

    protected function getManager(): ClientManager
    {
        if (! isset(self::$manager)) {
            self::$manager = new ClientManager([
                'options' => [
                    'debug' => $_ENV['LIVE_MAILBOX_DEBUG'] ?? false,
                ],
                'accounts' => [
                    'default' => [
                        'host' => getenv('LIVE_MAILBOX_HOST'),
                        'port' => getenv('LIVE_MAILBOX_PORT'),
                        'encryption' => getenv('LIVE_MAILBOX_ENCRYPTION'),
                        'validate_cert' => getenv('LIVE_MAILBOX_VALIDATE_CERT'),
                        'username' => getenv('LIVE_MAILBOX_USERNAME'),
                        'password' => getenv('LIVE_MAILBOX_PASSWORD'),
                        'protocol' => 'imap', // might also use imap, [pop3 or nntp (untested)]
                    ],
                ],
            ]);
        }

        return self::$manager;
    }

    protected function getClient(): Client
    {
        if (! getenv('LIVE_MAILBOX') ?? false) {
            $this->markTestSkipped('This test requires a live mailbox. Please set the LIVE_MAILBOX environment variable to run this test.');
        }

        return $this->getManager()->account('default');
    }

    protected function getSpecialChars(): string
    {
        return self::SPECIAL_CHARS;
    }

    protected function getFolder(string $folder_path = 'INDEX'): Folder
    {
        $client = $this->getClient();
        self::assertInstanceOf(Client::class, $client->connect());

        $folder = $client->getFolderByPath($folder_path);
        self::assertInstanceOf(Folder::class, $folder);

        return $folder;
    }

    protected function appendMessage(Folder $folder, string $message): Message
    {
        $status = $folder->select();
        if (! isset($status['uidnext'])) {
            $this->fail('No UIDNEXT returned');
        }

        $response = $folder->appendMessage($message);
        $valid_response = false;
        foreach ($response as $line) {
            if (str_starts_with($line, 'OK')) {
                $valid_response = true;
                break;
            }
        }
        if (! $valid_response) {
            $this->fail('Failed to append message: '.implode("\n", $response));
        }

        $message = $folder->messages()->getMessageByUid($status['uidnext']);
        self::assertInstanceOf(Message::class, $message);

        return $message;
    }

    protected function appendMessageTemplate(Folder $folder, string $template): Message
    {
        $content = file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', $template]));

        return $this->appendMessage($folder, $content);
    }

    protected function deleteFolder(?Folder $folder = null): bool
    {
        $response = $folder?->delete(false);

        if (is_array($response)) {
            $valid_response = false;
            foreach ($response as $line) {
                if (str_starts_with($line, 'OK')) {
                    $valid_response = true;
                    break;
                }
            }
            if (! $valid_response) {
                $this->fail('Failed to delete mailbox: '.implode("\n", $response));
            }

            return $valid_response;
        }

        return false;
    }
}
