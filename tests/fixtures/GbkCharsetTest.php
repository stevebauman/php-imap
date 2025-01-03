<?php

/*
* File: GbkCharsetTest.php
* Category: -
* Author: M.Goldenbaum
* Created: 09.03.23 02:24
* Updated: -
*
* Description:
*  -
*/

namespace Tests\fixtures;

/**
 * Class GbkCharsetTest.
 */
class GbkCharsetTest extends FixtureTestCase
{
    /**
     * Test the fixture gbk_charset.eml.
     */
    public function test_fixture(): void
    {
        $message = $this->getFixture('gbk_charset.eml');

        self::assertEquals('Nuu', $message->subject);
        self::assertEquals('Hi', $message->getTextBody());
        self::assertFalse($message->hasHTMLBody());
        self::assertEquals('2017-09-13 11:05:45', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        self::assertEquals('from@there.com', $message->from->first()->mail);
        self::assertEquals('to@here.com', $message->to->first()->mail);
    }
}
