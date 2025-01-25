<?php

namespace Tests\Issue;

use Tests\Integration\TestCase;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

class Issue413Test extends TestCase
{
    public function test_live_issue_email()
    {
        $folder = $this->getFolder('INBOX');
        $this->assertInstanceOf(Folder::class, $folder);

        /** @var Message $message */
        $_message = $this->appendMessageTemplate($folder, 'issue-413.eml');

        $message = $folder->messages()->getMessageByMsgn($_message->msgn);
        $this->assertEquals($message->uid, $_message->uid);

        $this->assertSame('Test Message', (string) $message->subject);
        $this->assertSame("This is just a test, so ignore it (if you can!)\r\n\r\nTony Marston", $message->getTextBody());

        $message->delete();
    }

    public function test_issue_email()
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', 'issue-413.eml']);
        $message = Message::fromFile($filename);

        $this->assertSame('Test Message', (string) $message->subject);
        $this->assertSame("This is just a test, so ignore it (if you can!)\r\n\r\nTony Marston", $message->getTextBody());
    }
}
