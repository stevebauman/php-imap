<?php

namespace Webklex\PHPIMAP;

class Address
{
    /**
     * Address attributes.
     */
    public string $personal = '';

    public string $mailbox = '';

    public string $host = '';

    public string $mail = '';

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
     * Return the stringified address.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->full ?: '';
    }

    /**
     * Return the serialized address.
     *
     * @return array
     */
    public function __serialize()
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
     * Convert instance to array.
     */
    public function toArray(): array
    {
        return $this->__serialize();
    }

    /**
     * Return the stringified attribute.
     */
    public function toString(): string
    {
        return $this->__toString();
    }
}
