<?php

namespace Tests\fixtures;

class PlainOnlyTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('plain_only.eml');

        $this->assertEquals('Nuu', $message->getSubject());
        $this->assertEquals('Hi', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from->first()->mail);
        $this->assertEquals('to@here.com', $message->to->first()->mail);
    }
}
