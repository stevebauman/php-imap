<?php

namespace Webklex\PHPIMAP\Events;

use Webklex\PHPIMAP\Message;

class FlagNewEvent extends Event
{
    public Message $message;

    public string $flag;

    /**
     * Create a new event instance.
     *
     * @var array
     *
     * @return void
     */
    public function __construct(array $arguments)
    {
        $this->message = $arguments[0];
        $this->flag = $arguments[1];
    }
}
