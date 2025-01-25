<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use Webklex\PHPIMAP\Attribute;

class AttributeTest extends TestCase
{
    public function test_string_attribute(): void
    {
        $attribute = new Attribute('foo', 'bar');

        $this->assertSame('bar', $attribute->toString());
        $this->assertSame('foo', $attribute->getName());
        $this->assertSame('foos', $attribute->setName('foos')->getName());
    }

    public function test_date_attribute(): void
    {
        $attribute = new Attribute('foo', '2022-12-26 08:07:14 GMT-0800');

        $this->assertInstanceOf(Carbon::class, $attribute->toDate());
        $this->assertSame('2022-12-26 08:07:14 GMT-0800', $attribute->toDate()->format('Y-m-d H:i:s T'));
    }

    public function test_array_attribute(): void
    {
        $attribute = new Attribute('foo', ['bar']);

        $this->assertSame('bar', $attribute->toString());

        $attribute->add('bars');
        $this->assertSame(true, $attribute->has(1));
        $this->assertSame('bars', $attribute->get(1));
        $this->assertSame(true, $attribute->contains('bars'));
        $this->assertSame('foo, bars', $attribute->set('foo')->toString());

        $attribute->remove();
        $this->assertSame('bars', $attribute->toString());

        $this->assertSame('bars, foos', $attribute->merge(['foos', 'bars'], true)->toString());
        $this->assertSame('bars, foos, foos, donk', $attribute->merge(['foos', 'donk'])->toString());

        $this->assertSame(4, $attribute->count());

        $this->assertSame('donk', $attribute->last());
        $this->assertSame('bars', $attribute->first());

        $this->assertSame(['bars', 'foos', 'foos', 'donk'], array_values($attribute->all()));
    }
}
