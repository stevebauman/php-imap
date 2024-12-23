<?php

/*
* File:     FolderNewEvent.php
* Category: Event
* Author:   M. Goldenbaum
* Created:  25.11.20 22:21
* Updated:  -
*
* Description:
*  -
*/

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Folder;

/**
 * Class FolderNewEvent.
 */
class FolderNewEvent extends Event
{
    public Folder $folder;

    /**
     * Create a new event instance.
     *
     * @var Folder[]
     *
     * @return void
     */
    public function __construct(array $folders)
    {
        $this->folder = $folders[0];
    }
}
