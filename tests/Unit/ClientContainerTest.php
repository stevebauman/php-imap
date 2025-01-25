<?php

namespace Tests\Unit;

use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientContainer;
use Webklex\PHPIMAP\Imap;

class ClientContainerTest extends TestCase
{
    protected ClientContainer $manager;

    protected function setUp(): void
    {
        $this->manager = ClientContainer::getNewInstance();
    }

    public function test_config_accessor_account(): void
    {
        $this->assertSame('default', ClientContainer::get('default'));
        $this->assertSame('d-M-Y', ClientContainer::get('date_format'));
        $this->assertSame(Imap::FT_PEEK, ClientContainer::get('options.fetch'));
        $this->assertSame([], ClientContainer::get('options.open'));
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
    }
}
