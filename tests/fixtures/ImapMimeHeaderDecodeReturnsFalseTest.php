<?php

namespace Tests\fixtures;

class ImapMimeHeaderDecodeReturnsFalseTest extends FixtureTestCase
{
    /**
     * Test the fixture imap_mime_header_decode_returns_false.eml.
     */
    public function test_fixture(): void
    {
        $message = $this->getFixture('imap_mime_header_decode_returns_false.eml');

        self::assertEquals('=?UTF-8?B?nnDusSNdG92w6Fuw61fMjAxOF8wMy0xMzMyNTMzMTkzLnBkZg==?=', $message->subject->first());
        self::assertEquals('Hi', $message->getTextBody());
        self::assertFalse($message->hasHTMLBody());
        self::assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from->first()->mail);
        self::assertEquals('to@here.com', $message->to->first()->mail);
    }
}
