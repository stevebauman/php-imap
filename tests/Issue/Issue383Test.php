<?php

namespace Tests\Issue;

use Tests\Integration\TestCase;
use Webklex\PHPIMAP\Folder;

class Issue383Test extends TestCase
{
    public function test_issue(): void
    {
        $client = $this->getClient();
        $client->connect();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'Entwürfe+']);

        $folder = $client->getFolder($folder_path);
        $this->deleteFolder($folder);

        $folder = $client->createFolder($folder_path, false);
        $this->assertInstanceOf(Folder::class, $folder);

        $folder = $this->getFolder($folder_path);
        $this->assertInstanceOf(Folder::class, $folder);

        $this->assertEquals('Entwürfe+', $folder->name);
        $this->assertEquals($folder_path, $folder->fullName);

        $folder_path = implode($delimiter, ['INBOX', 'Entw&APw-rfe+']);
        $this->assertEquals($folder_path, $folder->path);

        // Clean up
        if ($this->deleteFolder($folder) === false) {
            $this->fail('Could not delete folder: '.$folder->path);
        }
    }
}
