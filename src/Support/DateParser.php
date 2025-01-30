<?php

namespace Webklex\PHPIMAP\Support;

use Carbon\Carbon;
use Exception;

class DateParser
{
    /**
     * Main entry point for parsing dates.
     */
    public static function parse(string $rawDate): Carbon
    {
        $date = self::prepareRawDate($rawDate);

        try {
            return self::parseWithCarbon($date);
        } catch (Exception) {
            return self::parseWithPatterns($date);
        }
    }

    /**
     * Clean the raw date before parsing.
     */
    protected static function prepareRawDate(string $rawDate): string
    {
        // Fix a known +0580 offset.
        if (preg_match('/\+0580/', $rawDate)) {
            $rawDate = str_replace('+0580', '+0530', $rawDate);
        }

        // Trim whitespace.
        return trim(rtrim($rawDate));
    }

    /**
     * Perform the parse using Carbon.
     */
    protected static function parseWithCarbon(string $date): Carbon
    {
        if (str_contains($date, '&nbsp;')) {
            $date = str_replace('&nbsp;', ' ', $date);
        }

        if (str_contains($date, ' UT ')) {
            $date = str_replace(' UT ', ' UTC ', $date);
        }

        return Carbon::parse($date);
    }

    /**
     * Perform the parse using patterns.
     */
    protected static function parseWithPatterns(string $date): Carbon
    {
        switch (true) {
            case preg_match('/([0-9]{4}\.[0-9]{1,2}\.[0-9]{1,2}\-[0-9]{1,2}\.[0-9]{1,2}.[0-9]{1,2})+$/i', $date) > 0:
                $date = Carbon::createFromFormat('Y.m.d-H.i.s', $date);
                break;

            case preg_match('/([0-9]{2} [A-Z]{3} [0-9]{4} [0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2} [+-][0-9]{1,4} [0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2} [+-][0-9]{1,4})+$/i', $date) > 0:
                $date = self::handleDoubleOffsetPattern($date);
                break;

            case preg_match('/([A-Z]{2,4}\,\ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4})+$/i', $date) > 0:
                $date = self::handleWithLeadingAbbreviation($date);
                break;

            case preg_match('/([0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ UT)+$/i', $date) > 0:
            case preg_match('/([A-Z]{2,3}\,\ [0-9]{1,2}\ [A-Z]{2,3}\ ([0-9]{2}|[0-9]{4})\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ UT)+$/i', $date) > 0:
                // Append 'C' for 'UTC'
                $date .= 'C';
                break;

            case preg_match('/([A-Z]{2,3}\,\ [0-9]{1,2}[\,]\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4})+$/i', $date) > 0:
                $date = str_replace(',', '', $date);
                break;

                // Di., 15 Feb. 2022 06:52:44 +0100 (MEZ)/Di., 15 Feb. 2022 06:52:44 +0100 (MEZ)
            case preg_match('/([A-Z]{2,3}\.\,\ [0-9]{1,2}\ [A-Z]{2,3}\.\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4}\ \([A-Z]{3,4}\))\/([A-Z]{2,3}\.\,\ [0-9]{1,2}\ [A-Z]{2,3}\.\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4}\ \([A-Z]{3,4}\))+$/i', $date) > 0:
                $date = self::handleSlashedAbbreviationPattern($date);
                break;

                // fr., 25 nov. 2022 06:27:14 +0100/fr., 25 nov. 2022 06:27:14 +0100
            case preg_match('/([A-Z]{2,3}\.\,\ [0-9]{1,2}\ [A-Z]{2,3}\.\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4})\/([A-Z]{2,3}\.\,\ [0-9]{1,2}\ [A-Z]{2,3}\.\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4})+$/i', $date) > 0:
                $date = self::handleSimpleSlashedPattern($date);
                break;

            case preg_match('/([A-Z]{2,3}\,\ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ \+[0-9]{2,4}\ \(\+[0-9]{1,2}\))+$/i', $date) > 0:
            case preg_match('/([A-Z]{2,3}[\,|\ \,]\ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}.*)+$/i', $date) > 0:
            case preg_match('/([A-Z]{2,3}\,\ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4}\ \(.*)\)+$/i', $date) > 0:
            case preg_match('/([A-Z]{2,3}\, \ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4}\ \(.*)\)+$/i', $date) > 0:
            case preg_match('/([0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{2,4}\ [0-9]{2}\:[0-9]{2}\:[0-9]{2}\ [A-Z]{2}\ \-[0-9]{2}\:[0-9]{2}\ \([A-Z]{2,3}\ \-[0-9]{2}:[0-9]{2}\))+$/i', $date) > 0:
                $date = self::removeBracketedInfo($date);
                break;
        }

        return Carbon::parse($date);
    }

    /**
     * Handle the double-offset pattern.
     */
    protected static function handleDoubleOffsetPattern(string $date): string
    {
        $parts = explode(' ', $date);

        array_splice($parts, -2);

        return implode(' ', $parts);
    }

    /**
     * Handle patterns like: Aaa, 15 Feb 2022 06:52:44 +0100
     */
    protected static function handleWithLeadingAbbreviation(string $date): Carbon
    {
        $array = explode(',', $date);

        array_shift($array);

        return Carbon::createFromFormat('d M Y H:i:s O', trim(implode(',', $array)));
    }

    /**
     * Handle slashed pattern with abbreviations in parentheses.
     */
    protected static function handleSlashedAbbreviationPattern(string $date): Carbon
    {
        $dates = explode('/', $date);
        $date = array_shift($dates);

        $array = explode(',', $date);
        array_shift($array);
        $date = trim(implode(',', $array));

        $array = explode(' ', $date);
        array_pop($array);
        $date = trim(implode(' ', $array));

        return Carbon::createFromFormat('d M. Y H:i:s O', $date);
    }

    /**
     * Handle slashed pattern without abbreviations in parentheses.
     */
    protected static function handleSimpleSlashedPattern(string $date): Carbon
    {
        $dates = explode('/', $date);
        $date = array_shift($dates);

        $array = explode(',', $date);
        array_shift($array);
        $date = trim(implode(',', $array));

        return Carbon::createFromFormat('d M. Y H:i:s O', $date);
    }

    /**
     * Remove trailing bracketed info if present.
     */
    protected static function removeBracketedInfo(string $date): string
    {
        $array = explode('(', $date);
        $array = array_reverse($array);

        return trim(array_pop($array));
    }
}
