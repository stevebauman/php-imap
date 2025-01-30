<?php

namespace Tests\Fixture;

use Tests\InteractsWithFixtures;
use Tests\TestCase;
use Webklex\PHPIMAP\ClientContainer;

abstract class FixtureTestCase extends TestCase
{
    use InteractsWithFixtures;

    protected static ClientContainer $manager;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        self::$manager = ClientContainer::getNewInstance([
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

        return self::$manager;
    }
}
