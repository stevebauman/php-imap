<?php
/*
* File:     MessageNewEvent.php
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
 * Class MessageNewEvent.
 */
class MessageNewEvent extends Event
{
    public Message $message;

    /**
     * Create a new event instance.
     *
     * @var Message[]
     *
     * @return void
     */
    public function __construct(array $messages)
    {
        $this->message = $messages[0];
    }
}
