<?php

namespace Webklex\PHPIMAP;

use Illuminate\Support\Traits\ForwardsCalls;

/** @mixin ClientManager */
class ClientContainer
{
    use ForwardsCalls;

    /**
     * The current client manager instance.
     */
    protected ClientManager $manager;

    /**
     * The singleton manager instance.
     */
    protected static ClientContainer $instance;

    /**
     * Get or set the current instance of the container.
     */
    public static function getInstance(array|string $config = []): static
    {
        return static::$instance ?? static::getNewInstance($config);
    }

    /**
     * Set the container instance.
     */
    public static function setInstance(?self $container = null): ?static
    {
        return static::$instance = $container;
    }

    /**
     * Set and get a new instance of the container.
     */
    public static function getNewInstance(array|string $config = []): static
    {
        return static::setInstance(
            new static(new ClientManager($config))
        );
    }

    /**
     * Forward static calls onto the current instance.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::getInstance()->{$method}(...$parameters);
    }

    /**
     * Constructor.
     */
    public function __construct(ClientManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Forward method calls onto the client manager.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->manager, $method, $parameters);
    }

    /**
     * Set the current container instance available globally.
     */
    public function setAsGlobal(): void
    {
        static::setInstance($this);
    }
}
