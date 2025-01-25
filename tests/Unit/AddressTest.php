<?php

namespace Tests\Unit;

use Tests\TestCase;
use Webklex\PHPIMAP\Address;

class AddressTest extends TestCase
{
    protected array $data = [
        'personal' => 'Username',
        'mailbox' => 'info',
        'host' => 'domain.tld',
        'mail' => 'info@domain.tld',
        'full' => 'Username <info@domain.tld>',
    ];

    public function test_address(): void
    {
        $address = new Address((object) $this->data);

        $this->assertSame('Username', $address->personal);
        $this->assertSame('info', $address->mailbox);
        $this->assertSame('domain.tld', $address->host);
        $this->assertSame('info@domain.tld', $address->mail);
        $this->assertSame('Username <info@domain.tld>', $address->full);
    }

    public function test_address_to_string_conversion(): void
    {
        $address = new Address((object) $this->data);

        $this->assertSame('Username <info@domain.tld>', (string) $address);
    }

    public function test_address_serialization(): void
    {
        $address = new Address((object) $this->data);

        foreach ($address as $key => $value) {
            $this->assertSame($this->data[$key], $value);
        }
    }
}
