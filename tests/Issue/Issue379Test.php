<?php

namespace Tests\Issue;

use Tests\Integration\TestCase;

class Issue379Test extends TestCase
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
