<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Message;

class MessageMovedEvent extends Event
{
    /**
     * The old message instance.
     */
    public Message $oldMessage;

    /**
     * The new message instance.
     */
    public Message $newMessage;

    /**
     * Constructor.
     *
     * @param  Message[]  $messages
     */
    public function __construct(array $messages)
    {
        $this->oldMessage = $messages[0];
        $this->newMessage = $messages[1];
    }
}
