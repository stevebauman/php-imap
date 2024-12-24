<?php

namespace Webklex\PHPIMAP;

use Stringable;

class Address implements Stringable
{
    /**
     * The address personal.
     */
    public string $personal = '';

    /**
     * The address mailbox.
     */
    public string $mailbox = '';

    /**
     * The address host.
     */
    public string $host = '';

    /**
     * The address mail.
     */
    public string $mail = '';

    /**
     * The full address.
     */
    public string $full = '';

    /**
     * Address constructor.
     */
    public function __construct(object $object)
    {
        if (property_exists($object, 'personal')) {
            $this->personal = $object->personal ?? '';
        }
        if (property_exists($object, 'mailbox')) {
            $this->mailbox = $object->mailbox ?? '';
        }
        if (property_exists($object, 'host')) {
            $this->host = $object->host ?? '';
        }
        if (property_exists($object, 'mail')) {
            $this->mail = $object->mail ?? '';
        }
        if (property_exists($object, 'full')) {
            $this->full = $object->full ?? '';
        }
    }

    /**
     * Return the serialized address.
     */
    public function __serialize(): array
    {
        return [
            'personal' => $this->personal,
            'mailbox' => $this->mailbox,
            'host' => $this->host,
            'mail' => $this->mail,
            'full' => $this->full,
        ];
    }

    /**
     * Transform the instance to an array.
     */
    public function toArray(): array
    {
        return $this->__serialize();
    }

    /**
     * Transform the instance to a string.
     */
    public function toString(): string
    {
        return $this->__toString();
    }

    /**
     * Transform the instance to a string.
     */
    public function __toString(): string
    {
        return $this->full ?: '';
    }
}
