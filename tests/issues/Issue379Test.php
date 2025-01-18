<?php

/*
* File: Issue355Test.php
* Category: -
* Author: M.Goldenbaum
* Created: 10.01.23 10:48
* Updated: -
*
* Description:
*  -
*/

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
