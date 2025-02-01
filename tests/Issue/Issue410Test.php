<?php

namespace Tests\Issue;

use Tests\InteractsWithFixtures;
use Tests\TestCase;

class Issue410Test extends TestCase
{
    use InteractsWithFixtures;

    public function test_issue_email()
    {
        $message = $this->getMessageFixture('issue-410.eml');

        $this->assertSame('☆第132号　「ガーデン&エクステリア」専門店のためのＱ&Ａサロン　【月刊エクステリア・ワーク】', (string) $message->subject);

        $attachments = $message->getAttachments();

        $this->assertSame(1, $attachments->count());

        $attachment = $attachments->first();
        $this->assertSame('☆第132号　「ガーデン&エクステリア」専門店のためのＱ&Ａサロン　【月刊エクステリア・ワーク】', $attachment->filename);
        $this->assertSame('☆第132号　「ガーデン&エクステリア」専門店のためのＱ&Ａサロン　【月刊エクステリア・ワーク】', $attachment->name);
    }

    public function test_issue_email_b()
    {
        $message = $this->getMessageFixture('issue-410b.eml');

        $this->assertSame('386 - 400021804 - 19., Heiligenstädter Straße 80 - 0819306 - Anfrage Vergabevorschlag', (string) $message->subject);

        $attachments = $message->getAttachments();

        $this->assertSame(1, $attachments->count());

        $attachment = $attachments->first();
        $this->assertSame('2021_Mängelliste_0819306.xlsx', $attachment->description);
        $this->assertSame('2021_Mängelliste_0819306.xlsx', $attachment->filename);
        $this->assertSame('2021_Mängelliste_0819306.xlsx', $attachment->name);
    }

    public function test_issue_email_symbols()
    {
        $message = $this->getMessageFixture('issue-410symbols.eml');

        $attachments = $message->getAttachments();

        $this->assertSame(1, $attachments->count());

        $attachment = $attachments->first();
        $this->assertSame('Checkliste 10.,DAVIDGASSE 76-80;2;2.pdf', $attachment->description);
        $this->assertSame('Checkliste 10.,DAVIDGASSE 76-80;2;2.pdf', $attachment->filename);
        $this->assertSame('Checkliste 10.,DAVIDGASSE 76-80;2;2.pdf', $attachment->name);
    }
}
