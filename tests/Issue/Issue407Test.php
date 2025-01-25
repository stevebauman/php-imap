<?php

namespace Tests\Issue;

use Tests\Integration\TestCase;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

class Issue407Test extends TestCase
{
    public function test_issue()
    {
        $folder = $this->getFolder('INBOX');
        $this->assertInstanceOf(Folder::class, $folder);

        $message = $this->appendMessageTemplate($folder, 'plain.eml');
        $this->assertInstanceOf(Message::class, $message);

        $message->setFlag('Seen');

        $flags = $this->getClient()->getConnection()->flags($message->uid)->getValidatedData();

        $this->assertIsArray($flags);
        $this->assertSame(1, count($flags));
        $this->assertSame('\\Seen', $flags[$message->uid][0]);

        $message->delete();
    }
}
