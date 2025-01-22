<?php

namespace Tests;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

class ClientManagerTest extends TestCase
{
    protected ClientManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ClientManager;
    }

    public function test_config_accessor_account(): void
    {
        self::assertSame('default', ClientManager::get('default'));
        self::assertSame('d-M-Y', ClientManager::get('date_format'));
        self::assertSame(IMAP::FT_PEEK, ClientManager::get('options.fetch'));
        self::assertSame([], ClientManager::get('options.open'));
    }

    public function test_make_client(): void
    {
        self::assertInstanceOf(Client::class, $this->manager->make([]));
    }

    public function test_account_accessor(): void
    {
        self::assertSame('default', $this->manager->getDefaultAccount());
        self::assertNotEmpty($this->manager->account('default'));

        $this->manager->setDefaultAccount('foo');
        self::assertSame('foo', $this->manager->getDefaultAccount());
        $this->manager->setDefaultAccount('default');
    }

    public function test_set_config(): void
    {
        $config = [
            'default' => 'foo',
            'options' => [
                'fetch' => IMAP::ST_MSGN,
                'open' => 'foo',
            ],
        ];

        $cm = new ClientManager($config);

        self::assertSame('foo', $cm->getDefaultAccount());
        self::assertInstanceOf(Client::class, $cm->account('foo'));
        self::assertSame(IMAP::ST_MSGN, $cm->get('options.fetch'));
        self::assertSame(false, is_array($cm->get('options.open')));
    }
}
