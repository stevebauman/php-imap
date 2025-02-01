<?php

namespace Webklex\PHPIMAP\Support;

class HeaderParser
{
    /**
     * Parse mail headers from a string.
     *
     * @link https://php.net/manual/en/function.imap-rfc822-parse-headers.php
     */
    public static function parse(string $rawHeaders, bool $rfc822 = true): object
    {
        $headers = [];
        $imapHeaders = [];

        if (extension_loaded('imap') && $rfc822) {
            $rawImapHeaders = (array) imap_rfc822_parse_headers($rawHeaders);

            foreach ($rawImapHeaders as $key => $values) {
                $key = strtolower(str_replace('-', '_', $key));

                $imapHeaders[$key] = $values;
            }
        }

        $lines = explode("\r\n", preg_replace("/\r\n\s/", ' ', $rawHeaders));

        $prevHeader = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, "\n")) {
                $line = substr($line, 1);
            }

            if (str_starts_with($line, "\t")) {
                $line = substr($line, 1);
                $line = trim(rtrim($line));
                if ($prevHeader !== null) {
                    $headers[$prevHeader][] = $line;
                }
            } elseif (str_starts_with($line, ' ')) {
                $line = substr($line, 1);
                $line = trim(rtrim($line));
                if ($prevHeader !== null) {
                    if (! isset($headers[$prevHeader])) {
                        $headers[$prevHeader] = '';
                    }
                    if (is_array($headers[$prevHeader])) {
                        $headers[$prevHeader][] = $line;
                    } else {
                        $headers[$prevHeader] .= $line;
                    }
                }
            } else {
                if (($pos = strpos($line, ':')) > 0) {
                    $key = trim(rtrim(strtolower(substr($line, 0, $pos))));
                    $key = strtolower(str_replace('-', '_', $key));

                    $value = trim(rtrim(substr($line, $pos + 1)));
                    if (isset($headers[$key])) {
                        $headers[$key][] = $value;
                    } else {
                        $headers[$key] = [$value];
                    }
                    $prevHeader = $key;
                }
            }
        }

        foreach ($headers as $key => $values) {
            if (isset($imapHeaders[$key])) {
                continue;
            }

            $value = null;

            switch ((string) $key) {
                case 'from':
                case 'to':
                case 'cc':
                case 'bcc':
                case 'reply_to':
                case 'sender':
                    $value = static::decodeAddresses($values);
                    $headers[$key.'address'] = implode(', ', $values);
                    break;
                case 'subject':
                    $value = implode(' ', $values);
                    break;
                default:
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            if ($v == '') {
                                unset($values[$k]);
                            }
                        }

                        $availableValues = count($values);

                        if ($availableValues === 1) {
                            $value = array_pop($values);
                        } elseif ($availableValues === 2) {
                            $value = implode(' ', $values);
                        } elseif ($availableValues > 2) {
                            $value = array_values($values);
                        } else {
                            $value = '';
                        }
                    }
                    break;
            }

            $headers[$key] = $value;
        }

        return (object) array_merge($headers, $imapHeaders);
    }

    public static function split(string $header): array
    {
        $parts = [];
        $buffer = '';
        $inQuote = false;
        $length = strlen($header);

        for ($i = 0; $i < $length; $i++) {
            $char = $header[$i];

            if ($char === '"') {
                // Toggle the quote flag; assumes no escaped quotes.
                $inQuote = ! $inQuote;
                $buffer .= $char;
            } elseif ($char === ';' && ! $inQuote) {
                $parts[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        if (strlen($buffer) > 0) {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * Extract a given part as address array from a given header.
     */
    protected static function decodeAddresses(array $values, bool $rfc822 = true): array
    {
        $addresses = [];

        if (extension_loaded('mailparse') && $rfc822) {
            foreach ($values as $address) {
                foreach (mailparse_rfc822_parse_addresses($address) as $parsedAddress) {
                    if (isset($parsedAddress['address'])) {
                        $mailAddress = explode('@', $parsedAddress['address']);

                        if (count($mailAddress) == 2) {
                            $addresses[] = (object) [
                                'personal' => $parsedAddress['display'] ?? '',
                                'mailbox' => $mailAddress[0],
                                'host' => $mailAddress[1],
                            ];
                        }
                    }
                }
            }

            return $addresses;
        }

        foreach ($values as $address) {
            foreach (preg_split('/, ?(?=(?:[^"]*"[^"]*")*[^"]*$)/', $address) as $splitAddress) {
                $splitAddress = trim(rtrim($splitAddress));

                if (strpos($splitAddress, ',') == strlen($splitAddress) - 1) {
                    $splitAddress = substr($splitAddress, 0, -1);
                }

                if (preg_match(
                    '/^(?:(?P<name>.+)\s)?(?(name)<|<?)(?P<email>[^\s]+?)(?(name)>|>?)$/',
                    $splitAddress,
                    $matches
                )) {
                    $name = trim(rtrim($matches['name']));
                    $email = trim(rtrim($matches['email']));

                    [$mailbox, $host] = array_pad(explode('@', $email), 2, null);

                    $addresses[] = (object) [
                        'personal' => $name,
                        'mailbox' => $mailbox,
                        'host' => $host,
                    ];
                }
            }
        }

        return $addresses;
    }
}
