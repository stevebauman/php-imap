<?php
/*
* File:     MessageMovedEvent.php
* Category: Event
* Author:   M. Goldenbaum
* Created:  25.11.20 22:21
* Updated:  -
*
* Description:
*  -
*/

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Message;

/**
 * Class MessageMovedEvent.
 */
class MessageMovedEvent extends Event
{
    /** @var Message */
    public Message $old_message;

    /** @var Message */
    public Message $new_message;

    /**
     * Create a new event instance.
     *
     * @var Message[]
     *
     * @return void
     */
    public function __construct(array $messages)
    {
        $this->old_message = $messages[0];
        $this->new_message = $messages[1];
    }
}
