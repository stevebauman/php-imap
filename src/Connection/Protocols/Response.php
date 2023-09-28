<?php
/*
* File: Response.php
* Category: -
* Author: M.Goldenbaum
* Created: 30.12.22 19:46
* Updated: -
*
* Description:
*  -
*/

namespace Webklex\PHPIMAP\Connection\Protocols;

use Webklex\PHPIMAP\Exceptions\ResponseException;

/**
 * Class Response.
 */
class Response
{
    /**
     * The commands used to fetch or manipulate data.
     */
    protected array $commands = [];

    /**
     * The original response received.
     */
    protected array $response = [];

    /**
     * Errors that have occurred while fetching or parsing the response.
     */
    protected array $errors = [];

    /**
     * Result to be returned.
     *
     * @var mixed|null
     */
    protected mixed $result = null;

    /**
     * Noun to identify the request / response.
     */
    protected int $noun = 0;

    /**
     * Other related responses.
     */
    protected array $response_stack = [];

    /**
     * Debug flag.
     */
    protected bool $debug = false;

    /**
     * Can the response be empty?
     */
    protected bool $can_be_empty = false;

    /**
     * Create a new Response instance.
     */
    public function __construct(int $noun, bool $debug = false)
    {
        $this->debug = $debug;
        $this->noun = $noun > 0 ? $noun : (int) str_replace('.', '', (string) microtime(true));
    }

    /**
     * Make a new response instance.
     */
    public static function make(int $noun, array $commands = [], array $responses = [], bool $debug = false): Response
    {
        return (new self($noun, $debug))->setCommands($commands)->setResponse($responses);
    }

    /**
     * Create a new empty response.
     */
    public static function empty(bool $debug = false): Response
    {
        return new self(0, $debug);
    }

    /**
     * Stack another response.
     */
    public function stack(Response $response): void
    {
        $this->response_stack[] = $response;
    }

    /**
     * Get the associated response stack.
     */
    public function getStack(): array
    {
        return $this->response_stack;
    }

    /**
     * Get all assigned commands.
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Add a new command.
     */
    public function addCommand(string $command): Response
    {
        $this->commands[] = $command;

        return $this;
    }

    /**
     * Set and overwrite all commands.
     */
    public function setCommands(array $commands): Response
    {
        $this->commands = $commands;

        return $this;
    }

    /**
     * Get all set errors.
     */
    public function getErrors(): array
    {
        $errors = $this->errors;
        foreach ($this->getStack() as $response) {
            $errors = array_merge($errors, $response->getErrors());
        }

        return $errors;
    }

    /**
     * Set and overwrite all existing errors.
     */
    public function setErrors(array $errors): Response
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * Set the response.
     */
    public function addError(string $error): Response
    {
        $this->errors[] = $error;

        return $this;
    }

    /**
     * Set the response.
     *
     * @param  array  $response
     */
    public function addResponse(mixed $response): Response
    {
        $this->response[] = $response;

        return $this;
    }

    /**
     * Set the response.
     */
    public function setResponse(array $response): Response
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Get the assigned response.
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * Set the result data.
     */
    public function setResult(mixed $result): Response
    {
        $this->result = $result;

        return $this;
    }

    /**
     * Wrap a result bearing action.
     */
    public function wrap(callable $callback): Response
    {
        $this->result = call_user_func($callback, $this);

        return $this;
    }

    /**
     * Get the response data.
     */
    public function data(): mixed
    {
        if ($this->result !== null) {
            return $this->result;
        }

        return $this->getResponse();
    }

    /**
     * Get the response data as array.
     */
    public function array(): array
    {
        $data = $this->data();
        if (is_array($data)) {
            return $data;
        }

        return [$data];
    }

    /**
     * Get the response data as string.
     */
    public function string(): string
    {
        $data = $this->data();
        if (is_array($data)) {
            return implode(' ', $data);
        }

        return (string) $data;
    }

    /**
     * Get the response data as integer.
     */
    public function integer(): int
    {
        $data = $this->data();
        if (is_array($data) && isset($data[0])) {
            return (int) $data[0];
        }

        return (int) $data;
    }

    /**
     * Get the response data as boolean.
     */
    public function boolean(): bool
    {
        return (bool) $this->data();
    }

    /**
     * Validate and retrieve the response data.
     *
     * @throws ResponseException
     */
    public function validatedData(): mixed
    {
        return $this->validate()->data();
    }

    /**
     * Validate the response date.
     *
     * @throws ResponseException
     */
    public function validate(): Response
    {
        if ($this->failed()) {
            throw ResponseException::make($this, $this->debug);
        }

        return $this;
    }

    /**
     * Check if the Response can be considered successful.
     */
    public function successful(): bool
    {
        foreach (array_merge($this->getResponse(), $this->array()) as $data) {
            if (! $this->verify_data($data)) {
                return false;
            }
        }
        foreach ($this->getStack() as $response) {
            if (! $response->successful()) {
                return false;
            }
        }

        return ($this->boolean() || $this->canBeEmpty()) && ! $this->getErrors();
    }

    /**
     * Check if the Response can be considered failed.
     */
    public function verify_data(mixed $data): bool
    {
        if (is_array($data)) {
            foreach ($data as $line) {
                if (is_array($line)) {
                    if (! $this->verify_data($line)) {
                        return false;
                    }
                } else {
                    if (! $this->verify_line((string) $line)) {
                        return false;
                    }
                }
            }
        } else {
            if (! $this->verify_line((string) $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify a single line.
     */
    public function verify_line(string $line): bool
    {
        return ! str_starts_with($line, 'TAG'.$this->noun.' BAD ') && ! str_starts_with($line, 'TAG'.$this->noun.' NO ');
    }

    /**
     * Check if the Response can be considered failed.
     */
    public function failed(): bool
    {
        return ! $this->successful();
    }

    /**
     * Get the Response noun.
     */
    public function Noun(): int
    {
        return $this->noun;
    }

    /**
     * Set the Response to be allowed to be empty.
     *
     * @return $this
     */
    public function setCanBeEmpty(bool $can_be_empty): Response
    {
        $this->can_be_empty = $can_be_empty;

        return $this;
    }

    /**
     * Check if the Response can be empty.
     */
    public function canBeEmpty(): bool
    {
        return $this->can_be_empty;
    }
}
