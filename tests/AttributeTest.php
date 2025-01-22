<?php

namespace Tests;

use Carbon\Carbon;
use Webklex\PHPIMAP\Attribute;

class AttributeTest extends TestCase
{
    public function test_string_attribute(): void
    {
        $attribute = new Attribute('foo', 'bar');

        self::assertSame('bar', $attribute->toString());
        self::assertSame('foo', $attribute->getName());
        self::assertSame('foos', $attribute->setName('foos')->getName());
    }

    public function test_date_attribute(): void
    {
        $attribute = new Attribute('foo', '2022-12-26 08:07:14 GMT-0800');

        self::assertInstanceOf(Carbon::class, $attribute->toDate());
        self::assertSame('2022-12-26 08:07:14 GMT-0800', $attribute->toDate()->format('Y-m-d H:i:s T'));
    }

    public function test_array_attribute(): void
    {
        $attribute = new Attribute('foo', ['bar']);

        self::assertSame('bar', $attribute->toString());

        $attribute->add('bars');
        self::assertSame(true, $attribute->has(1));
        self::assertSame('bars', $attribute->get(1));
        self::assertSame(true, $attribute->contains('bars'));
        self::assertSame('foo, bars', $attribute->set('foo')->toString());

        $attribute->remove();
        self::assertSame('bars', $attribute->toString());

        self::assertSame('bars, foos', $attribute->merge(['foos', 'bars'], true)->toString());
        self::assertSame('bars, foos, foos, donk', $attribute->merge(['foos', 'donk'])->toString());

        self::assertSame(4, $attribute->count());

        self::assertSame('donk', $attribute->last());
        self::assertSame('bars', $attribute->first());

        self::assertSame(['bars', 'foos', 'foos', 'donk'], array_values($attribute->all()));
    }
}
