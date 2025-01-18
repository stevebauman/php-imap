<?php

/*
* File: Issue393Test.php
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
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;

class Issue393Test extends LiveMailboxTestCase
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
        self::assertCount(0, $folders);

        try {
            $client->getFolders(true, $pattern);
            $this->fail('Expected FolderFetchingException::class exception not thrown');
        } catch (FolderFetchingException $e) {
            self::assertInstanceOf(FolderFetchingException::class, $e);
        }
    }
}
