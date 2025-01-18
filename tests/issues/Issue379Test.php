<?php

namespace Tests\issues;

use Tests\live\LiveMailboxTestCase;

class Issue379Test extends LiveMailboxTestCase
{
    public function test_issue(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'plain.eml');
        $this->assertEquals(214, $message->getSize());

        // Clean up
        $this->assertTrue($message->delete());
    }
}
