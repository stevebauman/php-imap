<?php

namespace Tests\fixtures;

class NullContentCharsetTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('null_content_charset.eml');

        self::assertEquals('test', $message->getSubject());
        self::assertEquals('Hi!', $message->getTextBody());
        self::assertEquals('1.0', $message->mime_version);
        self::assertFalse($message->hasHTMLBody());

        self::assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from->first()->mail);
        self::assertEquals('to@here.com', $message->to->first()->mail);
    }
}
