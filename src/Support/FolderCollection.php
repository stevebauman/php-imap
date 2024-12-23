<?php

namespace Webklex\PHPIMAP\Support;

use Illuminate\Support\Collection;
use Webklex\PHPIMAP\Folder;

/**
 * @implements Collection<int, Folder>
 */
class FolderCollection extends PaginatedCollection {}
