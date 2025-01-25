<?php

namespace Tests\Fixture;

class KsC56011987HeadersTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getFixture('ks_c_5601-1987_headers.eml');

        $this->assertEquals('RE: 회원님께 Ersi님이 메시지를 보냈습니다.', $message->subject);
        $this->assertEquals('=?ks_c_5601-1987?B?yLi/+LTUsrIgRXJzabTUwMwguN69w8H2uKYgurizwr3AtM+02S4=?=', $message->thread_topic);
        $this->assertEquals('1.0', $message->mime_version);
        $this->assertEquals('Content', $message->getTextBody());
        $this->assertFalse($message->hasHTMLBody());
        $this->assertEquals('2017-09-27 10:48:51', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('to@here.com', $message->to->first()->mail);

        $from = $message->from->first();
        $this->assertEquals('김 현진', $from->personal);
        $this->assertEquals('from', $from->mailbox);
        $this->assertEquals('there.com', $from->host);
        $this->assertEquals('from@there.com', $from->mail);
        $this->assertEquals('김 현진 <from@there.com>', $from->full);
    }
}
