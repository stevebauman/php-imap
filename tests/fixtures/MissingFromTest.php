<?php

namespace Tests\fixtures;

class MissingFromTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('missing_from.eml');

        self::assertEquals('Nuu', $message->getSubject());
        self::assertEquals('Hi', $message->getTextBody());
        self::assertFalse($message->hasHTMLBody());
        self::assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertFalse($message->from->first());
        self::assertEquals('to@here.com', $message->to->first()->mail);
    }
}
