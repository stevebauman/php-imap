<?php

/*
* File:     FolderMovedEvent.php
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
 * Class FolderMovedEvent.
 */
class FolderMovedEvent extends Event
{
    public Folder $old_folder;

    public Folder $new_folder;

    /**
     * Create a new event instance.
     *
     * @var Folder[]
     *
     * @return void
     */
    public function __construct(array $folders)
    {
        $this->old_folder = $folders[0];
        $this->new_folder = $folders[1];
    }
}
