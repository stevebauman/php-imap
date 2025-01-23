<?php

namespace Webklex\PHPIMAP\Support\Masks;

use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;

class Mask
{
    use ForwardsCalls;

    /**
     * Theb parent instance.
     */
    protected mixed $parent;

    /**
     * The available attributes.
     */
    protected array $attributes = [];

    /**
     * Mask constructor.
     */
    public function __construct(mixed $parent)
    {
        $this->parent = $parent;

        if (method_exists($this->parent, 'getAttributes')) {
            $this->attributes = array_merge($this->attributes, $this->parent->getAttributes());
        }

        $this->boot();
    }

    /**
     * Handle dynamic method calls on the instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (strtolower(substr($method, 0, 3)) === 'get') {
            $name = Str::snake(substr($method, 3));

            if (isset($this->attributes[$name])) {
                return $this->attributes[$name];
            }
        } elseif (strtolower(substr($method, 0, 3)) === 'set') {
            $name = Str::snake(substr($method, 3));

            if (isset($this->attributes[$name])) {
                $this->attributes[$name] = array_pop($parameters);

                return $this->attributes[$name];
            }
        }

        return $this->forwardCallTo($this->parent, $method, $parameters);
    }

    /**
     * Boot method made to be used by any custom mask.
     */
    protected function boot(): void {}

    /**
     * Magic setter.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Magic getter.
     */
    public function __get(string $name): mixed
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return null;
    }

    /**
     * Get the parent instance.
     */
    public function getParent(): mixed
    {
        return $this->parent;
    }

    /**
     * Get all available attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
