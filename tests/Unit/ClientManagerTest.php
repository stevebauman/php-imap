<?php

namespace Tests\Unit;

use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Imap;

class ClientManagerTest extends TestCase
{
    protected ClientManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ClientManager;
    }

    public function test_config_accessor_account(): void
    {
        $this->assertSame('default', ClientManager::get('default'));
        $this->assertSame('d-M-Y', ClientManager::get('date_format'));
        $this->assertSame(Imap::FT_PEEK, ClientManager::get('options.fetch'));
        $this->assertSame([], ClientManager::get('options.open'));
    }

    public function test_make_client(): void
    {
        $this->assertInstanceOf(Client::class, $this->manager->make([]));
    }

    public function test_account_accessor(): void
    {
        $this->assertSame('default', $this->manager->getDefaultAccount());
        $this->assertNotEmpty($this->manager->account('default'));

        $this->manager->setDefaultAccount('foo');
        $this->assertSame('foo', $this->manager->getDefaultAccount());
        $this->manager->setDefaultAccount('default');
    }

    public function test_set_config(): void
    {
        $config = [
            'default' => 'foo',
            'options' => [
                'fetch' => Imap::ST_MSGN,
                'open' => 'foo',
            ],
        ];

        $cm = new ClientManager($config);

        $this->assertSame('foo', $cm->getDefaultAccount());
        $this->assertInstanceOf(Client::class, $cm->account('foo'));
        $this->assertSame(Imap::ST_MSGN, $cm->get('options.fetch'));
        $this->assertSame(false, is_array($cm->get('options.open')));
    }
}
