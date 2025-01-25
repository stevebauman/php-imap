<?php

namespace Tests\Fixture;

class HtmlOnlyTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('html_only.eml');

        $this->assertEquals('Nuu', $message->subject);
        $this->assertEquals('<html><body>Hi</body></html>', $message->getHTMLBody());
        $this->assertFalse($message->hasTextBody());
        $this->assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from->first()->mail);
        $this->assertEquals('to@here.com', $message->to->first()->mail);
    }
}
