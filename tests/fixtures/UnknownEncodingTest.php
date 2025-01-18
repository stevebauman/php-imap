<?php

namespace Tests\fixtures;

class UnknownEncodingTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('unknown_encoding.eml');

        self::assertEquals('test', $message->getSubject());
        self::assertEquals('MyPlain', $message->getTextBody());
        self::assertEquals('MyHtml', $message->getHTMLBody());
        self::assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from->first()->mail);
        self::assertEquals('to@here.com', $message->to->first()->mail);
    }
}
