<?php

namespace Tests\fixtures;

class BccTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('bcc.eml');

        self::assertEquals('test', $message->subject);
        self::assertEquals('<return-path@here.com>', $message->return_path);
        self::assertEquals('1.0', $message->mime_version);
        self::assertEquals('text/plain', $message->content_type);
        self::assertEquals('Hi!', $message->getTextBody());
        self::assertFalse($message->hasHTMLBody());
        self::assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from);
        self::assertEquals('to@here.com', $message->to);
        self::assertEquals('A_€@{è_Z <bcc@here.com>', $message->bcc);
        self::assertEquals('sender@here.com', $message->sender);
        self::assertEquals('reply-to@here.com', $message->reply_to);
    }
}
