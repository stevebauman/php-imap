<?php

namespace Tests\fixtures;

class GbkCharsetTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('gbk_charset.eml');

        self::assertEquals('Nuu', $message->subject);
        self::assertEquals('Hi', $message->getTextBody());
        self::assertFalse($message->hasHTMLBody());
        self::assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from->first()->mail);
        self::assertEquals('to@here.com', $message->to->first()->mail);
    }
}
