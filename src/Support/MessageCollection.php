<?php

namespace Webklex\PHPIMAP\Support;

use Illuminate\Support\Collection;
use Webklex\PHPIMAP\Message;

/**
 * @implements Collection<int, Message>
 */
class MessageCollection extends PaginatedCollection {}
