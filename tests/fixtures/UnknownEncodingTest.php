<?php

namespace Tests\fixtures;

class UnknownEncodingTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('unknown_encoding.eml');

        $this->assertEquals('test', $message->getSubject());
        $this->assertEquals('MyPlain', $message->getTextBody());
        $this->assertEquals('MyHtml', $message->getHTMLBody());
        $this->assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from->first()->mail);
        $this->assertEquals('to@here.com', $message->to->first()->mail);
    }
}
