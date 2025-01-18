<?php

namespace Tests\fixtures;

use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

abstract class FixtureTestCase extends TestCase
{
    protected static ClientManager $manager;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

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

        return self::$manager;
    }

    public function getFixture(string $template): Message
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..',  'messages', $template]);
        $message = Message::fromFile($filename);
        self::assertInstanceOf(Message::class, $message);

        return $message;
    }
}
