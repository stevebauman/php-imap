<?php

namespace Tests;

use Webklex\PHPIMAP\Message;

trait InteractsWithFixtures
{
    public function getFixture(string $template): Message
    {
        return Message::fromFile(
            implode(DIRECTORY_SEPARATOR, [__DIR__, 'messages', $template])
        );
    }
}
