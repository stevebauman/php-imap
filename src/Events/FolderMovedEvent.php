<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Folder;

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
