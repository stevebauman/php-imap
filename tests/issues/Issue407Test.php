<?php

namespace Tests\issues;

use Tests\live\LiveMailboxTestCase;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

class Issue407Test extends LiveMailboxTestCase
{
    public function test_issue()
    {
        $folder = $this->getFolder('INBOX');
        self::assertInstanceOf(Folder::class, $folder);

        $message = $this->appendMessageTemplate($folder, 'plain.eml');
        self::assertInstanceOf(Message::class, $message);

        $message->setFlag('Seen');

        $flags = $this->getClient()->getConnection()->flags($message->uid)->getValidatedData();

        self::assertIsArray($flags);
        self::assertSame(1, count($flags));
        self::assertSame('\\Seen', $flags[$message->uid][0]);

        $message->delete();
    }
}
