<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Address;

class AddressTest extends TestCase
{
    /**
     * Test data.
     *
     * @var array|string[]
     */
    protected array $data = [
        'personal' => 'Username',
        'mailbox' => 'info',
        'host' => 'domain.tld',
        'mail' => 'info@domain.tld',
        'full' => 'Username <info@domain.tld>',
    ];

    /**
     * Address test.
     */
    public function test_address(): void
    {
        $address = new Address((object) $this->data);

        self::assertSame('Username', $address->personal);
        self::assertSame('info', $address->mailbox);
        self::assertSame('domain.tld', $address->host);
        self::assertSame('info@domain.tld', $address->mail);
        self::assertSame('Username <info@domain.tld>', $address->full);
    }

    /**
     * Test Address to string conversion.
     */
    public function test_address_to_string_conversion(): void
    {
        $address = new Address((object) $this->data);

        self::assertSame('Username <info@domain.tld>', (string) $address);
    }

    /**
     * Test Address serialization.
     */
    public function test_address_serialization(): void
    {
        $address = new Address((object) $this->data);

        foreach ($address as $key => $value) {
            self::assertSame($this->data[$key], $value);
        }
    }
}
