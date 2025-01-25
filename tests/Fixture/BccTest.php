<?php

namespace Tests\Fixture;

class BccTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('bcc.eml');

        $this->assertEquals('test', $message->subject);
        $this->assertEquals('<return-path@here.com>', $message->return_path);
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertEquals('text/plain', $message->content_type);
        $this->assertEquals('Hi!', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from);
        $this->assertEquals('to@here.com', $message->to);
        $this->assertEquals('A_€@{è_Z <bcc@here.com>', $message->bcc);
        $this->assertEquals('sender@here.com', $message->sender);
        $this->assertEquals('reply-to@here.com', $message->reply_to);
    }
}
