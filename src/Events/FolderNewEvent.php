<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Folder;

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
