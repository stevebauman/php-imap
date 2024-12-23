<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Message;

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
