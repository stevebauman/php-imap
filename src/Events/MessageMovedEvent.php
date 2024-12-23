<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Message;

class MessageMovedEvent extends Event
{
    public Message $old_message;

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
