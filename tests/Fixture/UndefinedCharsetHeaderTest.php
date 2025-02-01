<?php

namespace Tests\Fixture;

use Webklex\PHPIMAP\Address;

class UndefinedCharsetHeaderTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('undefined_charset_header.eml');

        $this->assertEquals('<monitor@bla.bla>', $message->get('x-real-to'));
        $this->assertEquals('1.0', $message->get('mime-version'));
        $this->assertEquals('Mon, 27 Feb 2017 13:21:44 +0930', $message->get('Resent-Date'));
        $this->assertEquals('<postmaster@bla.bla>', $message->get('Resent-From'));
        $this->assertEquals('BlaBla', $message->get('X-Stored-In'));
        $this->assertEquals('<info@bla.bla>', $message->get('Return-Path'));
        $this->assertEquals([
            'from <postmaster@bla.bla>  by bla.bla (CommuniGate Pro RULE 6.1.13)  with RULE id 14057804; Mon, 27 Feb 2017 13:21:44 +0930',
        ], $message->get('Received')->all());
        $this->assertEquals(')', $message->getHTMLBody());
        $this->assertFalse($message->hasTextBody());
        $this->assertEquals('2017-02-27 03:51:29', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));

        $from = $message->from->first();
        $this->assertInstanceOf(Address::class, $from);

        $this->assertEquals('myGov', $from->personal);
        $this->assertEquals('info', $from->mailbox);
        $this->assertEquals('bla.bla', $from->host);
        $this->assertEquals('info@bla.bla', $from->mail);
        $this->assertEquals('myGov <info@bla.bla>', $from->full);

        $this->assertEquals('sales@bla.bla', $message->to->first()->mail);
        $this->assertEquals('Submit your tax refund | Australian Taxation Office.', $message->subject);
        $this->assertEquals('201702270351.BGF77614@bla.bla', $message->message_id);
    }
}
