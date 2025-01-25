<?php

namespace Tests\Fixture;

class MissingDateTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('missing_date.eml');

        $this->assertEquals('Nuu', $message->getSubject());
        $this->assertEquals('Hi', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertFalse($message->date->first());
        $this->assertEquals('from@here.com', $message->from->first()->mail);
        $this->assertEquals('to@here.com', $message->to->first()->mail);
    }
}
