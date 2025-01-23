<?php

namespace Tests\fixtures;

class NullContentCharsetTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('null_content_charset.eml');

        $this->assertEquals('test', $message->getSubject());
        $this->assertEquals('Hi!', $message->getTextBody());
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertFalse($message->hasHTMLBody());

        $this->assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from->first()->mail);
        $this->assertEquals('to@here.com', $message->to->first()->mail);
    }
}
