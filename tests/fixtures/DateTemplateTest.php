<?php

namespace Tests\fixtures;

use Webklex\PHPIMAP\Exceptions\InvalidMessageDateException;
use Webklex\PHPIMAP\Message;

class DateTemplateTest extends FixtureTestCase
{
    protected array $dates = [
        'Fri, 5 Apr 2019 12:10:49 +0200' => '2019-04-05 10:10:49',
        '04 Jan 2018 10:12:47 UT' => '2018-01-04 10:12:47',
        '22 Jun 18 03:56:36 PM -05:00 (GMT -05:00)' => '2018-06-22 20:56:36',
        'Sat, 31 Aug 2013 20:08:23 +0580' => '2013-08-31 14:38:23',
        'Fri, 1 Feb 2019 01:30:04 +0600 (+06)' => '2019-01-31 19:30:04',
        'Mon, 4 Feb 2019 04:03:49 -0300 (-03)' => '2019-02-04 07:03:49',
        'Sun, 6 Apr 2008 21:24:33 UT' => '2008-04-06 21:24:33',
        'Wed, 11 Sep 2019 15:23:06 +0600 (+06)' => '2019-09-11 09:23:06',
        '14 Sep 2019 00:10:08 UT +0200' => '2019-09-14 00:10:08',
        'Tue, 08 Nov 2022 18:47:20 +0000 14:03:33 +0000' => '2022-11-08 18:47:20',
        'Sat, 10, Dec 2022 09:35:19 +0100' => '2022-12-10 08:35:19',
        'Thur, 16 Mar 2023 15:33:07 +0400' => '2023-03-16 11:33:07',
        'fr., 25 nov. 2022 06:27:14 +0100/fr., 25 nov. 2022 06:27:14 +0100' => '2022-11-25 05:27:14',
        'Di., 15 Feb. 2022 06:52:44 +0100 (MEZ)/Di., 15 Feb. 2022 06:52:44 +0100 (MEZ)' => '2022-02-15 05:52:44',
    ];

    public function test_fixture(): void
    {
        try {
            $message = $this->getFixture('date-template.eml');
            $this->fail('Expected InvalidMessageDateException');
        } catch (InvalidMessageDateException $e) {
            $this->assertTrue(true);
        }

        self::$manager->setConfig([
            'options' => [
                'fallback_date' => '2021-01-01 00:00:00',
            ],
        ]);
        $message = $this->getFixture('date-template.eml');

        $this->assertEquals('test', $message->subject);
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertEquals('Hi!', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertEquals('2021-01-01 00:00:00', $message->date->first()->timezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('from@there.com', (string) $message->from);
        $this->assertEquals('to@here.com', $message->to);

        self::$manager->setConfig([
            'options' => [
                'fallback_date' => null,
            ],
        ]);

        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..',  'messages', 'date-template.eml']);
        $blob = file_get_contents($filename);
        $this->assertNotFalse($blob);

        foreach ($this->dates as $date => $expected) {
            $message = Message::fromString(str_replace('%date_raw_header%', $date, $blob));
            $this->assertEquals('test', $message->subject);
            $this->assertEquals('1.0', $message->mime_version);
            $this->assertEquals('Hi!', $message->getTextBody());
            $this->assertFalse($message->hasHTMLBody());
            $this->assertEquals($expected, $message->date->first()->timezone('UTC')->format('Y-m-d H:i:s'), "Date \"$date\" should be \"$expected\"");
            $this->assertEquals('from@there.com', (string) $message->from);
            $this->assertEquals('to@here.com', $message->to);
        }
    }
}
