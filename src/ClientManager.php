<?php

namespace Webklex\PHPIMAP;

use Illuminate\Support\Arr;
use Illuminate\Support\Traits\ForwardsCalls;
use Webklex\PHPIMAP\Support\Arr as SupportArr;

class ClientManager
{
    use ForwardsCalls;

    /**
     * The configuration array.
     */
    protected array $config = [];

    /**
     * The array of resolved accounts.
     */
    protected array $accounts = [];

    /**
     * Constructor.
     */
    public function __construct(array|string $config = [])
    {
        $this->setConfig($config);
    }

    /**
     * Handle dynamic method calls on the manager.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->account(), $method, $parameters);
    }

    /**
     * Safely create a new client instance which is not listed in accounts.
     */
    public function make(array $config): Client
    {
        return new Client($config);
    }

    /**
     * Get a config value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Get the mask for a given section.
     *
     * @param  string  $section  section name such as "message" or "attachment"
     */
    public function getMask(string $section): ?string
    {
        $defaultMasks = $this->get('masks');

        if (! isset($defaultMasks[$section])) {
            return null;
        }

        if (class_exists($defaultMasks[$section])) {
            return $defaultMasks[$section];
        }

        return null;
    }

    /**
     * Resolve an account instance.
     */
    public function account(?string $name = null): Client
    {
        $name = $name ?: $this->getDefaultAccount();

        return $this->accounts[$name] ??= $this->resolve($name);
    }

    /**
     * Resolve an account.
     */
    protected function resolve(string $name): Client
    {
        return new Client($this->getClientConfig($name));
    }

    /**
     * Get the account configuration.
     */
    protected function getClientConfig(?string $name): array
    {
        if (empty($name) || $name === 'null') {
            return ['driver' => 'null'];
        }

        $account = $this->config['accounts'][$name] ?? [];

        return is_array($account) ? $account : [];
    }

    /**
     * Get the name of the default account.
     */
    public function getDefaultAccount(): string
    {
        return $this->config['default'];
    }

    /**
     * Set the name of the default account.
     */
    public function setDefaultAccount(string $name): void
    {
        $this->config['default'] = $name;
    }

    /**
     * Merge the vendor settings with the local config.
     *
     * The default account identifier will be used as default for any missing account parameters.
     * If however the default account is missing a parameter the package default account parameter will be used.
     * This can be disabled by setting imap.default in your config file to 'false'
     */
    public function setConfig(array|string $config): self
    {
        if (is_string($config)) {
            $config = require $config;
        }

        $vendorConfig = $this->getVendorConfig();

        $config = SupportArr::mergeRecursiveDistinct($vendorConfig, $config);

        if (isset($config['default'])) {
            if (isset($config['accounts']) && $config['default']) {
                $defaultConfig = $vendorConfig['accounts']['default'];

                if (isset($config['accounts'][$config['default']])) {
                    $defaultConfig = array_merge($defaultConfig, $config['accounts'][$config['default']]);
                }

                if (is_array($config['accounts'])) {
                    foreach ($config['accounts'] as $accountKey => $account) {
                        $config['accounts'][$accountKey] = array_merge($defaultConfig, $account);
                    }
                }
            }
        }

        $this->config = $config;

        return $this;
    }

    /**
     * Get the vendor configuration.
     */
    protected function getVendorConfig(): array
    {
        return require __DIR__.'/config/imap.php';
    }
}
