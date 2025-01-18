<?php

/*
* File: Issue413Test.php
* Category: Test
* Author: M.Goldenbaum
* Created: 23.06.23 21:09
* Updated: -
*
* Description:
*  -
*/

namespace Tests\issues;

use Tests\live\LiveMailboxTestCase;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

class Issue413Test extends LiveMailboxTestCase
{
    public function test_live_issue_email()
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        /** @var Message $message */
        $_message = $this->appendMessageTemplate($folder, 'issue-413.eml');

        $message = $folder->messages()->getMessageByMsgn($_message->msgn);
        self::assertEquals($message->uid, $_message->uid);

        self::assertSame('Test Message', (string) $message->subject);
        self::assertSame("This is just a test, so ignore it (if you can!)\r\n\r\nTony Marston", $message->getTextBody());

        $message->delete();
    }

    public function test_issue_email()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-413.eml']);
        $message = Message::fromFile($filename);

        self::assertSame('Test Message', (string) $message->subject);
        self::assertSame("This is just a test, so ignore it (if you can!)\r\n\r\nTony Marston", $message->getTextBody());
    }
}
