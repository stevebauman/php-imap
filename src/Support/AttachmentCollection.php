<?php

namespace Webklex\PHPIMAP\Support;

use Illuminate\Support\Collection;
use Webklex\PHPIMAP\Attachment;

/**
 * @implements Collection<int, Attachment>
 */
class AttachmentCollection extends PaginatedCollection {}
