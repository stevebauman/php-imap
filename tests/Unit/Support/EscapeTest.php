<?php

namespace Tests\Unit\Support;

use Tests\TestCase;
use Webklex\PHPIMAP\Support\Escape;

class EscapeTest extends TestCase
{
    public function test_string_without_newline(): void
    {
        $this->assertSame('"test"', Escape::string('test'));
    }

    public function test_string_with_newline(): void
    {
        $this->assertSame(
            ['{9}', "test\nline"],
            Escape::string("test\nline")
        );
    }

    public function test_string_with_quotes_and_slashes(): void
    {
        $this->assertSame(
            '"te\\"st\\\\"',
            Escape::string('te"st\\')
        );
    }

    public function test_multiple_strings(): void
    {
        $this->assertSame(
            ['"hello"', '"world"'],
            Escape::string('hello', 'world')
        );
    }

    public function test_list_simple(): void
    {
        $this->assertSame(
            '(hello world)',
            Escape::list(['hello', 'world'])
        );
    }

    public function test_list_nested(): void
    {
        $this->assertSame(
            '(hello (nested values) world)',
            Escape::list(['hello', ['nested', 'values'], 'world'])
        );
    }
}
