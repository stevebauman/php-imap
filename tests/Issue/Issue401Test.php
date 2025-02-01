<?php

namespace Tests\Issue;

use Tests\InteractsWithFixtures;
use Tests\TestCase;

class Issue401Test extends TestCase
{
    use InteractsWithFixtures;

    public function test_issue_email()
    {
        $message = $this->getMessageFixture('issue-401.eml');

        $this->assertSame('1;00pm Client running few minutes late', (string) $message->subject);
    }
}
