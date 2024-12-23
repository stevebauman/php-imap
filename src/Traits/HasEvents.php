<?php

namespace Webklex\PHPIMAP\Traits;

use Webklex\PHPIMAP\Events\Event;
use Webklex\PHPIMAP\Exceptions\EventNotFoundException;

trait HasEvents
{
    /**
     * Event holder.
     */
    protected array $events = [];

    /**
     * Set a specific event.
     */
    public function setEvent(string $section, string $event, mixed $class): void
    {
        if (isset($this->events[$section])) {
            $this->events[$section][$event] = $class;
        }
    }

    /**
     * Set all events.
     */
    public function setEvents(array $events): void
    {
        $this->events = $events;
    }

    /**
     * Get a specific event callback.
     *
     *
     * @throws EventNotFoundException
     */
    public function getEvent(string $section, string $event): Event|string
    {
        if (isset($this->events[$section])) {
            return $this->events[$section][$event];
        }

        throw new EventNotFoundException;
    }

    /**
     * Get all events.
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
