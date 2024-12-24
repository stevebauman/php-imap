<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Folder;

class FolderMovedEvent extends Event
{
    /**
     * The old folder instance.
     */
    public Folder $oldFolder;

    /**
     * The new folder instance.
     */
    public Folder $newFolder;

    /**
     * Constructor.
     *
     * @param  Folder[]  $folders
     */
    public function __construct(array $folders)
    {
        $this->oldFolder = $folders[0];
        $this->newFolder = $folders[1];
    }
}
