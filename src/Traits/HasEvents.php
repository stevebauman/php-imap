<?php

namespace Webklex\PHPIMAP\Traits;

use Webklex\PHPIMAP\Events\Event;
use Webklex\PHPIMAP\Exceptions\EventNotFoundException;

trait HasEvents
{
    /**
     * The available events.
     */
    protected array $events = [];

    /**
     * Dispatch a specific event.
     */
    public function dispatch(string $section, string $event, mixed ...$args): void
    {
        $event = $this->getEvent($section, $event);

        $event::dispatch(...$args);
    }

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
     */
    public function getEvent(string $section, string $event): Event|string
    {
        if (isset($this->events[$section])) {
            return $this->events[$section][$event];
        }

        throw new EventNotFoundException;
    }

    /**
     * Get all available events.
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
