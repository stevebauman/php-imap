<?php

namespace Webklex\PHPIMAP;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Traits\ForwardsCalls;
use Webklex\PHPIMAP\Exceptions\InvalidMessageDateException;
use Webklex\PHPIMAP\Support\DateParser;
use Webklex\PHPIMAP\Support\HeaderParser;

class Header
{
    use ForwardsCalls;

    /**
     * The raw header.
     */
    public string $raw = '';

    /**
     * Config holder.
     */
    protected array $config = [];

    /**
     * The header attributes.
     *
     * @var Attribute[]|array
     */
    protected array $attributes = [];

    /**
     * The fallback Encoding.
     */
    public string $fallbackEncoding = 'UTF-8';

    /**
     * Constructor.
     */
    public function __construct(string $rawHeader)
    {
        $this->raw = $rawHeader;
        $this->config = ClientContainer::get('options');
        $this->parse();
    }

    /**
     * Handle dynamic method calls on the instance.
     *
     * @return Attribute|mixed
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (str_starts_with($method, 'get')) {
            $name = preg_replace('/(.)(?=[A-Z])/u', '$1_', substr(strtolower($method), 3));

            if (in_array($name, array_keys($this->attributes))) {
                return $this->attributes[$name];
            }
        }

        static::throwBadMethodCallException($method);
    }

    /**
     * Get a specific header attribute.
     */
    public function __get(string $name): Attribute
    {
        return $this->get($name);
    }

    /**
     * Get a specific header attribute.
     */
    public function get(string $name): Attribute
    {
        $name = str_replace(['-', ' '], '_', strtolower($name));

        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return new Attribute($name);
    }

    /**
     * Determine if an attribute exists.
     */
    public function has(string $name): bool
    {
        $name = str_replace(['-', ' '], '_', strtolower($name));

        return isset($this->attributes[$name]);
    }

    /**
     * Set a specific attribute.
     *
     * @param  array|mixed  $value
     */
    public function set(string $name, mixed $value, bool $strict = false): Attribute|array
    {
        if (isset($this->attributes[$name]) && ! $strict) {
            $this->attributes[$name]->add($value, true);
        } else {
            $this->attributes[$name] = new Attribute($name, $value);
        }

        return $this->attributes[$name];
    }

    /**
     * Perform a regex match all on the raw header and return the first result.
     */
    public function find(string $pattern): ?string
    {
        if (! preg_match_all($pattern, $this->raw, $matches)) {
            return null;
        }

        if (! isset($matches[1])) {
            return null;
        }

        if (count($matches[1]) > 0) {
            return $matches[1][0];
        }

        return null;
    }

    /**
     * Try to find a boundary if possible.
     */
    public function getBoundary(): ?string
    {
        $regex = $this->config['boundary'] ?? '/boundary=(.*?(?=;)|(.*))/i';

        if ($boundary = $this->find($regex)) {
            return $this->clearBoundaryString($boundary);
        }

        return null;
    }

    /**
     * Remove all unwanted chars from a given boundary.
     */
    protected function clearBoundaryString(string $str): string
    {
        return str_replace(['"', '\r', '\n', "\n", "\r", ';', "\s"], '', $str);
    }

    /**
     * Parse the raw headers.
     *
     * @throws InvalidMessageDateException
     */
    protected function parse(): void
    {
        $header = HeaderParser::parse($this->raw);

        $this->extractAddresses($header);

        if (property_exists($header, 'subject')) {
            $this->set('subject', $this->decode($header->subject));
        }

        if (property_exists($header, 'references')) {
            $this->set('references', array_map(function ($item) {
                return str_replace(['<', '>'], '', $item);
            }, explode(' ', $header->references)));
        }

        if (property_exists($header, 'message_id')) {
            $this->set('message_id', str_replace(['<', '>'], '', $header->message_id));
        }

        if (property_exists($header, 'in_reply_to')) {
            $this->set('in_reply_to', str_replace(['<', '>'], '', $header->in_reply_to));
        }

        $this->parseDate($header);

        foreach ($header as $key => $value) {
            $key = trim(rtrim(strtolower($key)));

            if (! isset($this->attributes[$key])) {
                $this->set($key, $value);
            }
        }

        $this->extractHeaderExtensions();
        $this->findPriority();
    }

    /**
     * Decode MIME header elements.
     *
     * @link https://php.net/manual/en/function.imap-mime-header-decode.php
     *
     * @param  string  $text  The MIME text
     * @return array The decoded elements are returned in an array of objects, where each
     *               object has two properties, charset and text.
     */
    public function mimeHeaderDecode(string $text): array
    {
        if (extension_loaded('imap')) {
            $result = imap_mime_header_decode($text);

            return is_array($result) ? $result : [];
        }

        $charset = $this->getEncoding($text);

        return [(object) [
            'charset' => $charset,
            'text' => $this->convertEncoding($text, $charset),
        ]];
    }

    /**
     * Check if a given pair of strings has been decoded.
     */
    protected function notDecoded(string $encoded, string $decoded): bool
    {
        return str_starts_with($decoded, '=?')
            && strlen($decoded) - 2 === strpos($decoded, '?=')
            && str_contains($encoded, $decoded);
    }

    /**
     * Convert the encoding.
     */
    public function convertEncoding(mixed $str, string $from = 'ISO-8859-2', string $to = 'UTF-8'): mixed
    {
        $from = EncodingAliases::get($from, $this->fallbackEncoding);
        $to = EncodingAliases::get($to, $this->fallbackEncoding);

        if ($from === $to) {
            return $str;
        }

        return EncodingAliases::convert($str, $from, $to);
    }

    /**
     * Get the encoding of a given abject.
     */
    public function getEncoding(object|string $structure): string
    {
        if (property_exists($structure, 'parameters')) {
            foreach ($structure->parameters as $parameter) {
                if (strtolower($parameter->attribute) == 'charset') {
                    return EncodingAliases::get($parameter->value, $this->fallbackEncoding);
                }
            }
        } elseif (property_exists($structure, 'charset')) {
            return EncodingAliases::get($structure->charset, $this->fallbackEncoding);
        } elseif (is_string($structure) === true) {
            $result = mb_detect_encoding($structure);

            return $result === false ? $this->fallbackEncoding : $result;
        }

        return $this->fallbackEncoding;
    }

    /**
     * Test if a given value is utf-8 encoded.
     */
    protected function isUtf8(string $value): bool
    {
        return str_starts_with(strtolower($value), '=?utf-8?');
    }

    /**
     * Try to decode a specific header.
     */
    public function decode(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->decodeArray($value);
        }

        $original = $value;
        $decoder = $this->config['decoder']['message'];

        if ($value !== null) {
            if ($decoder === 'utf-8') {
                $decodedValues = $this->mimeHeaderDecode($value);
                $tempValue = '';

                foreach ($decodedValues as $decodedValue) {
                    $tempValue .= $this->convertEncoding($decodedValue->text, $decodedValue->charset);
                }

                if ($tempValue) {
                    $value = $tempValue;
                } elseif (extension_loaded('imap')) {
                    $value = imap_utf8($value);
                } elseif (function_exists('iconv_mime_decode')) {
                    $value = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
                } else {
                    $value = mb_decode_mimeheader($value);
                }
            } elseif ($decoder === 'iconv') {
                $value = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            } elseif ($this->isUtf8($value)) {
                $value = mb_decode_mimeheader($value);
            }

            if ($this->notDecoded($original, $value)) {
                $value = $this->convertEncoding($original, $this->getEncoding($original));
            }
        }

        return $value;
    }

    /**
     * Decode a given array.
     */
    protected function decodeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = $this->decode($value);
        }

        return $values;
    }

    /**
     * Try to extract the priority from a given raw header string.
     */
    protected function findPriority(): void
    {
        $priority = $this->get('x_priority');

        $priority = match ((int) "$priority") {
            Imap::MESSAGE_PRIORITY_HIGHEST => Imap::MESSAGE_PRIORITY_HIGHEST,
            Imap::MESSAGE_PRIORITY_HIGH => Imap::MESSAGE_PRIORITY_HIGH,
            Imap::MESSAGE_PRIORITY_NORMAL => Imap::MESSAGE_PRIORITY_NORMAL,
            Imap::MESSAGE_PRIORITY_LOW => Imap::MESSAGE_PRIORITY_LOW,
            Imap::MESSAGE_PRIORITY_LOWEST => Imap::MESSAGE_PRIORITY_LOWEST,
            default => Imap::MESSAGE_PRIORITY_UNKNOWN,
        };

        $this->set('priority', $priority);
    }

    /**
     * Extract a given part as address array from a given header.
     */
    protected function extractAddresses(object $header): void
    {
        foreach (['from', 'to', 'cc', 'bcc', 'reply_to', 'sender'] as $key) {
            if (property_exists($header, $key)) {
                $this->set($key, $this->parseAddresses($header->$key));
            }
        }
    }

    /**
     * Parse Addresses.
     */
    protected function parseAddresses(mixed $list): array
    {
        $addresses = [];

        if (! is_array($list)) {
            return $addresses;
        }

        foreach ($list as $item) {
            $address = (object) $item;

            if (! property_exists($address, 'mailbox')) {
                $address->mailbox = false;
            }

            if (! property_exists($address, 'host')) {
                $address->host = false;
            }

            if (! property_exists($address, 'personal')) {
                $address->personal = false;
            } else {
                $personalParts = $this->mimeHeaderDecode($address->personal);

                $address->personal = '';
                foreach ($personalParts as $p) {
                    $address->personal .= $this->convertEncoding($p->text, $this->getEncoding($p));
                }

                if (str_starts_with($address->personal, "'")) {
                    $address->personal = str_replace("'", '', $address->personal);
                }
            }

            if ($address->host == '.SYNTAX-ERROR.') {
                $address->host = '';
            }

            if ($address->mailbox == 'UNEXPECTED_DATA_AFTER_ADDRESS') {
                $address->mailbox = '';
            }

            $address->mail = ($address->mailbox && $address->host) ? $address->mailbox.'@'.$address->host : false;
            $address->full = ($address->personal) ? $address->personal.' <'.$address->mail.'>' : $address->mail;

            $addresses[] = new Address($address);
        }

        return $addresses;
    }

    /**
     * Search and extract potential header extensions.
     */
    protected function extractHeaderExtensions(): void
    {
        foreach ($this->attributes as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            } else {
                $value = (string) $value;
            }

            // Only parse strings and don't parse any attributes like the user-agent.
            if (! in_array($key, ['user-agent', 'subject', 'received'])) {
                if (str_contains($value, ';') && str_contains($value, '=')) {
                    $_attributes = $this->readAttribute($value);
                    foreach ($_attributes as $_key => $_value) {
                        if ($_value === '') {
                            $this->set($key, $_key);
                        }
                        if (! isset($this->attributes[$_key])) {
                            $this->set($_key, $_value);
                        }
                    }
                }
            }
        }
    }

    /**
     * Read a given attribute string.
     */
    private function readAttribute(string $rawAttribute): array
    {
        $parsedRawAttributes = [];
        $currentKey = '';
        $currentValue = '';
        $isInsideQuotedString = false;
        $isParsingKey = true;
        $isEscaped = false;

        foreach (str_split($rawAttribute) as $char) {
            if ($isEscaped) {
                $isEscaped = false;

                continue;
            }

            if ($isInsideQuotedString) {
                if ($char === '\\') {
                    $isEscaped = true;
                } elseif ($char === '"' && $currentValue !== '') {
                    $isInsideQuotedString = false;
                } else {
                    $currentValue .= $char;
                }
            } else {
                if ($isParsingKey) {
                    if ($char === '"') {
                        $isInsideQuotedString = true;
                    } elseif ($char === ';') {
                        $parsedRawAttributes[$currentKey] = $currentValue;
                        $currentKey = '';
                        $currentValue = '';
                        $isParsingKey = true;
                    } elseif ($char === '=') {
                        $isParsingKey = false;
                    } else {
                        $currentKey .= $char;
                    }
                } else {
                    if ($char === '"' && $currentValue === '') {
                        $isInsideQuotedString = true;
                    } elseif ($char === ';') {
                        $parsedRawAttributes[$currentKey] = $currentValue;
                        $currentKey = '';
                        $currentValue = '';
                        $isParsingKey = true;
                    } else {
                        $currentValue .= $char;
                    }
                }
            }
        }

        // Capture the final key/value pair
        $parsedRawAttributes[$currentKey] = $currentValue;

        // Normalize and combine attributes
        $attributes = [];

        foreach ($parsedRawAttributes as $key => $value) {
            // Remove any trailing '*'
            if (($pos = strpos($key, '*')) !== false) {
                $key = substr($key, 0, $pos);
            }

            $key = strtolower(trim($key));
            $value = str_replace(["\r", "\n"], '', $value);
            $value = trim($value);

            if (! isset($attributes[$key])) {
                $attributes[$key] = '';
            }

            // Remove surrounding quotes if present
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }

            $attributes[$key] .= $value;
        }

        return $attributes;
    }

    /**
     * Exception handling for invalid dates.
     *
     * Known bad and "invalid" formats:
     * ^ Datetime                                   ^ Problem                           ^ Cause
     * | Mon, 20 Nov 2017 20:31:31 +0800 (GMT+8:00) | Double timezone specification     | A Windows feature
     * | Thu, 8 Nov 2018 08:54:58 -0200 (-02)       |
     * |                                            | and invalid timezone (max 6 char) |
     * | 04 Jan 2018 10:12:47 UT                    | Missing letter "C"                | Unknown
     * | Thu, 31 May 2018 18:15:00 +0800 (added by) | Non-standard details added by the | Unknown
     * |                                            | mail server                       |
     * | Sat, 31 Aug 2013 20:08:23 +0580            | Invalid timezone                  | PHPMailer bug https://sourceforge.net/p/phpmailer/mailman/message/6132703/
     *
     * Please report any new invalid timestamps to [#45](https://github.com/Webklex/php-imap/issues)
     *
     * @throws InvalidMessageDateException
     */
    protected function parseDate(object $header): void
    {
        if (! property_exists($header, 'date')) {
            return;
        }

        try {
            $parsedDate = DateParser::parse($header->date);
        } catch (Exception $e) {
            if (! isset($this->config['fallback_date'])) {
                throw new InvalidMessageDateException('Invalid message date. ID:'.$this->get('message_id').' Date:'.$header->date, 1100, $e);
            } else {
                $parsedDate = Carbon::parse($this->config['fallback_date']);
            }
        }

        $this->set('date', $parsedDate);
    }

    /**
     * Get all available attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set all header attributes.
     */
    public function setAttributes(array $attributes): Header
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Set the configuration used for parsing a raw header.
     */
    public function setConfig(array $config): Header
    {
        $this->config = $config;

        return $this;
    }
}
