<?php

namespace Tests\fixtures;

class ReferencesTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('references.eml');

        $this->assertEquals('', $message->subject);
        $this->assertEquals("Hi\r\nHow are you?", $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertFalse($message->date->first());

        $this->assertEquals('b9e87bd5e661a645ed6e3b832828fcc5@example.com', $message->in_reply_to);
        $this->assertEquals('', $message->from->first()->personal);
        $this->assertEquals('UNKNOWN', $message->from->first()->host);
        $this->assertEquals('no_host@UNKNOWN', $message->from->first()->mail);
        $this->assertFalse($message->to->first());

        $this->assertEquals([
            '231d9ac57aec7d8c1a0eacfeab8af6f3@example.com',
            '08F04024-A5B3-4FDE-BF2C-6710DE97D8D9@example.com',
        ], $message->getReferences()->all());

        $this->assertEquals([
            'This one: is "right" <ding@dong.com>',
            'No-address@UNKNOWN',
        ], $message->cc->map(function ($address) {
            /** @var \Webklex\PHPIMAP\Address $address */
            return $address->full;
        }));
    }
}
