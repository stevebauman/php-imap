<?php

namespace Tests\issues;

use Tests\TestCase;
use Webklex\PHPIMAP\Message;

class Issue410Test extends TestCase
{
    public function test_issue_email()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-410.eml']);
        $message = Message::fromFile($filename);

        self::assertSame('☆第132号　「ガーデン&エクステリア」専門店のためのＱ&Ａサロン　【月刊エクステリア・ワーク】', (string) $message->subject);

        $attachments = $message->getAttachments();

        self::assertSame(1, $attachments->count());

        $attachment = $attachments->first();
        self::assertSame('☆第132号　「ガーデン&エクステリア」専門店のためのＱ&Ａサロン　【月刊エクステリア・ワーク】', $attachment->filename);
        self::assertSame('☆第132号　「ガーデン&エクステリア」専門店のためのＱ&Ａサロン　【月刊エクステリア・ワーク】', $attachment->name);
    }

    public function test_issue_email_b()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-410b.eml']);
        $message = Message::fromFile($filename);

        self::assertSame('386 - 400021804 - 19., Heiligenstädter Straße 80 - 0819306 - Anfrage Vergabevorschlag', (string) $message->subject);

        $attachments = $message->getAttachments();

        self::assertSame(1, $attachments->count());

        $attachment = $attachments->first();
        self::assertSame('2021_Mängelliste_0819306.xlsx', $attachment->description);
        self::assertSame('2021_Mängelliste_0819306.xlsx', $attachment->filename);
        self::assertSame('2021_Mängelliste_0819306.xlsx', $attachment->name);
    }
}
