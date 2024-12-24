<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Message;

class MessageNewEvent extends Event
{
    /**
     * The message instance.
     */
    public Message $message;

    /**
     * Constructor.
     *
     * @param  Message[]  $messages
     */
    public function __construct(array $messages)
    {
        $this->message = $messages[0];
    }
}
