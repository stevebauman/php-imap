<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Message;

class FlagNewEvent extends Event
{
    /**
     * The message instance.
     */
    public Message $message;

    /**
     * The flag that was set.
     */
    public string $flag;

    /**
     * Constructor.
     */
    public function __construct(array $arguments)
    {
        $this->message = $arguments[0];
        $this->flag = $arguments[1];
    }
}
