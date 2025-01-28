<?php

namespace Tests\Integration;

use Tests\TestCase as BaseTestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientContainer;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

abstract class TestCase extends BaseTestCase
{
    const SPECIAL_CHARS = 'A_\\|!"£$%&()=?àèìòùÀÈÌÒÙ<>-@#[]_ß_б_π_€_✔_你_يد_Z_';

    protected function getManager(): ClientContainer
    {
        return ClientContainer::getNewInstance([
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
                ],
            ],
        ]);
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

        $client->connect();

        $folder = $client->getFolderByPath($folder_path);

        $this->assertInstanceOf(Folder::class, $folder);

        return $folder;
    }

    protected function appendMessage(Folder $folder, string $message): Message
    {
        $status = $folder->select();

        if (! isset($status['uidnext'])) {
            $this->fail('No UIDNEXT returned');
        }

        $response = $folder->appendMessage($message);

        $validResponse = false;

        foreach ($response as $line) {
            if (str_starts_with($line, 'OK')) {
                $validResponse = true;

                break;
            }
        }

        if (! $validResponse) {
            $this->fail('Failed to append message: '.implode("\n", $response));
        }

        $message = $folder->messages()->getMessageByUid($status['uidnext']);

        $this->assertInstanceOf(Message::class, $message);

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
            $validResponse = false;

            foreach ($response as $line) {
                if (str_starts_with($line, 'OK')) {
                    $validResponse = true;
                    break;
                }
            }

            if (! $validResponse) {
                $this->fail('Failed to delete mailbox: '.implode("\n", $response));
            }

            return $validResponse;
        }

        return false;
    }
}
