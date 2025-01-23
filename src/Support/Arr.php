<?php

namespace Webklex\PHPIMAP\Support;

class Arr
{
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
    public static function mergeRecursiveDistinct(array ...$arrays): array
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
                    $base[$key] = static::mergeRecursiveDistinct($base[$key], $value);
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
