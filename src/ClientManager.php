<?php

namespace Webklex\PHPIMAP;

use Illuminate\Support\Arr;
use Illuminate\Support\Traits\ForwardsCalls;

class ClientManager
{
    use ForwardsCalls;

    /**
     * The singleton configuration array.
     */
    public static array $config = [];

    /**
     * The array of resolved accounts.
     */
    protected array $accounts = [];

    /**
     * ClientManager constructor.
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
    public static function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(self::$config, $key, $default);
    }

    /**
     * Get the mask for a given section.
     *
     * @param  string  $section  section name such as "message" or "attachment"
     */
    public static function getMask(string $section): ?string
    {
        $defaultMasks = ClientManager::get('masks');

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
        if ($name === null || $name === 'null' || $name === '') {
            return ['driver' => 'null'];
        }

        $account = self::$config['accounts'][$name] ?? [];

        return is_array($account) ? $account : [];
    }

    /**
     * Get the name of the default account.
     */
    public function getDefaultAccount(): string
    {
        return self::$config['default'];
    }

    /**
     * Set the name of the default account.
     */
    public function setDefaultAccount(string $name): void
    {
        self::$config['default'] = $name;
    }

    /**
     * Merge the vendor settings with the local config.
     *
     * The default account identifier will be used as default for any missing account parameters.
     * If however the default account is missing a parameter the package default account parameter will be used.
     * This can be disabled by setting imap.default in your config file to 'false'
     */
    public function setConfig(array|string $config): ClientManager
    {
        if (is_string($config)) {
            $config = require $config;
        }

        $config_key = 'imap';
        $path = __DIR__.'/config/'.$config_key.'.php';

        $vendor_config = require $path;

        $config = $this->array_merge_recursive_distinct($vendor_config, $config);

        if (is_array($config)) {
            if (isset($config['default'])) {
                if (isset($config['accounts']) && $config['default']) {
                    $default_config = $vendor_config['accounts']['default'];

                    if (isset($config['accounts'][$config['default']])) {
                        $default_config = array_merge($default_config, $config['accounts'][$config['default']]);
                    }

                    if (is_array($config['accounts'])) {
                        foreach ($config['accounts'] as $account_key => $account) {
                            $config['accounts'][$account_key] = array_merge($default_config, $account);
                        }
                    }
                }
            }
        }

        self::$config = $config;

        return $this;
    }

    /**
     * Marge arrays recursively and distinct.
     *
     * Merges any number of arrays / parameters recursively, replacing
     * entries with string keys with values from latter arrays.
     * If the entry or the next value to be assigned is an array, then it
     * automatically treats both arguments as an array.
     * Numeric entries are appended, not replaced, but only if they are
     * unique
     *
     * @link   http://www.php.net/manual/en/function.array-merge-recursive.php#96201
     *
     * @author Mark Roduner <mark.roduner@gmail.com>
     */
    protected function array_merge_recursive_distinct(array ...$arrays): array
    {
        $base = array_shift($arrays);

        // From https://stackoverflow.com/a/173479
        $isAssoc = function (array $arr) {
            if ($arr === []) {
                return false;
            }

            return array_keys($arr) !== range(0, count($arr) - 1);
        };

        if (! is_array($base)) {
            $base = empty($base) ? [] : [$base];
        }

        foreach ($arrays as $append) {
            if (! is_array($append)) {
                $append = [$append];
            }

            foreach ($append as $key => $value) {
                if (! array_key_exists($key, $base) and ! is_numeric($key)) {
                    $base[$key] = $value;

                    continue;
                }

                if (
                    (
                        is_array($value)
                        && $isAssoc($value)
                    )
                    || (
                        is_array($base[$key])
                        && $isAssoc($base[$key])
                    )
                ) {
                    // If the arrays are not associates we don't want to array_merge_recursive_distinct
                    // else merging $baseConfig['dispositions'] = ['attachment', 'inline'] with $customConfig['dispositions'] = ['attachment']
                    // results in $resultConfig['dispositions'] = ['attachment', 'inline']
                    $base[$key] = $this->array_merge_recursive_distinct($base[$key], $value);
                } elseif (is_numeric($key)) {
                    if (! in_array($value, $base)) {
                        $base[] = $value;
                    }
                } else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }
}
