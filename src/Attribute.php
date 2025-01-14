<?php

namespace Webklex\PHPIMAP;

use ArrayAccess;
use Carbon\Carbon;

class Attribute implements ArrayAccess
{
    /**
     * Attribute name.
     */
    protected string $name;

    /**
     * Value holder.
     */
    protected array $values = [];

    /**
     * Attribute constructor.
     */
    public function __construct(string $name, mixed $value = null)
    {
        $this->setName($name);
        $this->add($value);
    }

    /**
     * Handle class invocation calls.
     */
    public function __invoke(): array|string
    {
        if ($this->count() > 1) {
            return $this->toArray();
        }

        return $this->toString();
    }

    /**
     * Return the serialized address.
     *
     * @return array
     */
    public function __serialize()
    {
        return $this->values;
    }

    /**
     * Return the stringified attribute.
     *
     * @return string
     */
    public function __toString()
    {
        return implode(', ', $this->values);
    }

    /**
     * Return the stringified attribute.
     */
    public function toString(): string
    {
        return $this->__toString();
    }

    /**
     * Convert instance to array.
     */
    public function toArray(): array
    {
        return $this->__serialize();
    }

    /**
     * Convert first value to a date object.
     */
    public function toDate(): Carbon
    {
        $date = $this->first();
        if ($date instanceof Carbon) {
            return $date;
        }

        return Carbon::parse($date);
    }

    /**
     * Determine if a value exists at a given key.
     *
     * @param  int|string  $key
     */
    public function has(mixed $key = 0): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Determine if a value exists at a given key.
     *
     * @param  int|string  $key
     */
    public function exist(mixed $key = 0): bool
    {
        return $this->has($key);
    }

    /**
     * Check if the attribute contains the given value.
     */
    public function contains(mixed $value): bool
    {
        return in_array($value, $this->values, true);
    }

    /**
     * Get a value by a given key.
     */
    public function get(int|string $key = 0): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * Set the value by a given key.
     */
    public function set(mixed $value, mixed $key = 0): Attribute
    {
        if (is_null($key)) {
            $this->values[] = $value;
        } else {
            $this->values[$key] = $value;
        }

        return $this;
    }

    /**
     * Unset a value by a given key.
     */
    public function remove(int|string $key = 0): Attribute
    {
        if (isset($this->values[$key])) {
            unset($this->values[$key]);
        }

        return $this;
    }

    /**
     * Add one or more values to the attribute.
     *
     * @param  array|mixed  $value
     */
    public function add(mixed $value, bool $strict = false): Attribute
    {
        if (is_array($value)) {
            return $this->merge($value, $strict);
        } elseif ($value !== null) {
            $this->attach($value, $strict);
        }

        return $this;
    }

    /**
     * Merge a given array of values with the current values array.
     */
    public function merge(array $values, bool $strict = false): Attribute
    {
        foreach ($values as $value) {
            $this->attach($value, $strict);
        }

        return $this;
    }

    /**
     * Attach a given value to the current value array.
     */
    public function attach($value, bool $strict = false): Attribute
    {
        if ($strict === true) {
            if ($this->contains($value) === false) {
                $this->values[] = $value;
            }
        } else {
            $this->values[] = $value;
        }

        return $this;
    }

    /**
     * Set the attribute name.
     */
    public function setName($name): Attribute
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the attribute name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get all values.
     */
    public function all(): array
    {
        reset($this->values);

        return $this->values;
    }

    /**
     * Get the first value if possible.
     */
    public function first(): mixed
    {
        return reset($this->values);
    }

    /**
     * Get the last value if possible.
     */
    public function last(): mixed
    {
        return end($this->values);
    }

    /**
     * Get the number of values.
     */
    public function count(): int
    {
        return count($this->values);
    }

    /**
     * @see  ArrayAccess::offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @see  ArrayAccess::offsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @see  ArrayAccess::offsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($value, $offset);
    }

    /**
     * @see  ArrayAccess::offsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * Run a callback over all the values.
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->values);
    }
}
