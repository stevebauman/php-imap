<?php

namespace Tests\Issue;

use Tests\Integration\TestCase;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;

class Issue393Test extends TestCase
{
    public function test_issue(): void
    {
        $client = $this->getClient();
        $client->connect();

        $delimiter = $this->getManager()->get('options.delimiter');
        $pattern = implode($delimiter, ['doesnt_exist', '%']);

        $folder = $client->getFolder('doesnt_exist');
        $this->deleteFolder($folder);

        $folders = $client->getFolders(true, $pattern, true);
        $this->assertCount(0, $folders);

        try {
            $client->getFolders(true, $pattern);
            $this->fail('Expected FolderFetchingException::class exception not thrown');
        } catch (FolderFetchingException $e) {
            $this->assertInstanceOf(FolderFetchingException::class, $e);
        }
    }
}
