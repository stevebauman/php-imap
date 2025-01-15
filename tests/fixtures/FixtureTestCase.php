<?php

/*
* File: FixtureTestCase.php
* Category: -
* Author: M.Goldenbaum
* Created: 09.03.23 02:24
* Updated: -
*
* Description:
*  -
*/

namespace Tests\fixtures;

use PHPUnit\Framework\TestCase;
use ReflectionException;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\InvalidMessageDateException;
use Webklex\PHPIMAP\Exceptions\MaskNotFoundException;
use Webklex\PHPIMAP\Exceptions\MessageContentFetchingException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Message;

/**
 * Class FixtureTestCase.
 */
abstract class FixtureTestCase extends TestCase
{
    /**
     * Client manager.
     */
    protected static ClientManager $manager;

    /**
     * FixtureTestCase constructor.
     */
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

    /**
     * Get a fixture message.
     *
     * @throws ReflectionException
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function getFixture(string $template): Message
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..',  'messages', $template]);
        $message = Message::fromFile($filename);
        self::assertInstanceOf(Message::class, $message);

        return $message;
    }
}
