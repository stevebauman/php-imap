<?php

namespace Tests;

use Webklex\PHPIMAP\Message;

trait InteractsWithFixtures
{
    protected function getMessageFixture(string $filename): Message
    {
        return Message::fromString($this->getFixtureContents($filename));
    }

    protected function getFixtureContents(string $filename)
    {
        return file_get_contents($this->getFixturePath($filename));
    }

    protected function getFixturePath(string $filename)
    {
        return implode(DIRECTORY_SEPARATOR, [__DIR__, 'messages', $filename]);
    }
}
