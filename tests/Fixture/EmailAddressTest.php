<?php

namespace Tests\Fixture;

class EmailAddressTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getMessageFixture('email_address.eml');

        $this->assertEquals('', $message->subject);
        $this->assertEquals('123@example.com', $message->message_id);
        $this->assertEquals("Hi\r\nHow are you?", $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertFalse($message->date->first());
        $this->assertEquals('no_host@UNKNOWN', (string) $message->from);
        $this->assertEquals('', $message->to);
        $this->assertEquals('This one: is "right" <ding@dong.com>, No-address@UNKNOWN', $message->cc);
    }
}
