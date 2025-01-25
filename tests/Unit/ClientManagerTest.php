<?php

namespace Tests\Unit;

use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Imap;

class ClientManagerTest extends TestCase
{
    public function test_set_config(): void
    {
        $cm = new ClientManager([
            'default' => 'foo',
            'options' => [
                'fetch' => Imap::ST_MSGN,
                'open' => 'foo',
            ],
        ]);

        $this->assertSame('foo', $cm->getDefaultAccount());
        $this->assertInstanceOf(Client::class, $cm->account('foo'));
        $this->assertSame(Imap::ST_MSGN, $cm->get('options.fetch'));
        $this->assertSame(false, is_array($cm->get('options.open')));
    }
}
