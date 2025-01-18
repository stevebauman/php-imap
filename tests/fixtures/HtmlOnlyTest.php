<?php

namespace Tests\fixtures;

class HtmlOnlyTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('html_only.eml');

        self::assertEquals('Nuu', $message->subject);
        self::assertEquals('<html><body>Hi</body></html>', $message->getHTMLBody());
        self::assertFalse($message->hasTextBody());
        self::assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from->first()->mail);
        self::assertEquals('to@here.com', $message->to->first()->mail);
    }
}
