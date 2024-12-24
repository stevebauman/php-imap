<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Folder;

class FolderNewEvent extends Event
{
    /**
     * The folder instance.
     */
    public Folder $folder;

    /**
     * Constructor.
     *
     * @param  Folder[]  $folders
     */
    public function __construct(array $folders)
    {
        $this->folder = $folders[0];
    }
}
