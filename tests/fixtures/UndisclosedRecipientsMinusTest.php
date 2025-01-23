<?php

namespace Tests\fixtures;

class UndisclosedRecipientsMinusTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('undisclosed_recipients_minus.eml');

        $this->assertEquals('test', $message->subject);
        $this->assertEquals('Hi!', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', $message->from);
        $this->assertEquals([
            'undisclosed-recipients',
            '',
        ], $message->to->map(function ($item) {
            return $item->mailbox;
        }));
    }
}
