<?php

namespace Tests\fixtures;

class UndisclosedRecipientsMinusTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('undisclosed_recipients_minus.eml');

        self::assertEquals('test', $message->subject);
        self::assertEquals('Hi!', $message->getTextBody());
        self::assertFalse($message->hasHTMLBody());
        self::assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from);
        self::assertEquals([
            'undisclosed-recipients',
            '',
        ], $message->to->map(function ($item) {
            return $item->mailbox;
        }));
    }
}
