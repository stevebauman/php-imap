<?php

namespace Tests\fixtures;

class ImapMimeHeaderDecodeReturnsFalseTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('imap_mime_header_decode_returns_false.eml');

        $this->assertEquals('=?UTF-8?B?nnDusSNdG92w6Fuw61fMjAxOF8wMy0xMzMyNTMzMTkzLnBkZg==?=', $message->subject->first());
        $this->assertEquals('Hi', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from->first()->mail);
        $this->assertEquals('to@here.com', $message->to->first()->mail);
    }
}
