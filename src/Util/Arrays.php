<?php

namespace Hail\Util;

use Hail\Util\Exception\RegexpException;

/**
 * Class Arrays
 *
 * @package Hail\Util
 * @author  Feng Hao <flyinghail@msn.com>
 */
class Arrays
{
    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param  array  $array
     * @param  string $prepend
     *
     * @return array
     */
    public function dot(array $array, string $prepend = ''): array
    {
        $results = [
            0 => [],
        ];

        foreach ($array as $key => $value) {
            if (\is_array($value) && !empty($value)) {
                $results[] = $this->dot($value, $prepend . $key . '.');
            } else {
                $results[0][$prepend . $key] = $value;
            }
        }

        return \array_merge(...$results);
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param array  $array
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(array $array, string $key = null, $default = null)
    {
        if ($key === null) {
            return $array;
        }

        if ($array === []) {
            return $default;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (\explode('.', $key) as $segment) {
            if (\is_array($array) && isset($array[$segment])) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * @param array  $array
     * @param string $key
     * @param mixed  $value
     */
    public function set(array &$array, string $key, $value)
    {
        foreach (\explode('.', $key) as $k) {
            if (!isset($array[$k]) || !\is_array($array[$k])) {
                $array[$k] = [];
            }
            $array = &$array[$k];
        }

        $array = $value;
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     *
     * @param array    $array
     * @param string[] ...$keys
     *
     * @return bool
     */
    public function has(array $array, string ...$keys): bool
    {
        if ($array === [] || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (isset($array[$key])) {
                continue;
            }

            $subKeyArray = $array;
            foreach (\explode('.', $key) as $segment) {
                if (\is_array($subKeyArray) && isset($subKeyArray[$segment])) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param array    $array
     * @param string[] ...$keys
     *
     * @return void
     */
    public function delete(array &$array, string ...$keys)
    {
        if ($keys === []) {
            return;
        }

        $original = &$array;
        foreach ($keys as $key) {
            // if the exact key exists in the top-level, remove it
            if (isset($array[$key])) {
                unset($array[$key]);
                continue;
            }

            // clean up before each pass
            $array = &$original;

            $parts = \explode('.', $key);
            $delKey = \array_pop($parts);
            foreach ($parts as $part) {
                if (isset($array[$part]) && \is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[$delKey]);
        }
    }

    /**
     * Determines if an array is associative.
     *
     * An array is "associative" if it doesn't have sequential numerical keys beginning with zero.
     *
     * @param  array $array
     *
     * @return bool
     */
    public function isAssoc(array $array): bool
    {
        if (!isset($array[0])) {
            return true;
        }

        $keys = \array_keys($array);

        return \array_keys($keys) !== $keys;
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public function filter(array $array): array
    {
        static $fun;

        if ($fun === null) {
            $fun = static function ($v) {
                return $v !== false && $v !== null;
            };
        }

        return \array_filter($array, $fun);
    }

    /**
     * Searches the array for a given key and returns the offset if successful.
     *
     * @param array      $arr
     * @param int|string $key
     *
     * @return int|null|string offset if it is found, null otherwise
     */
    public function searchKey(array $arr, $key)
    {
        $foo = [$key => null];

        return ($tmp = \array_search(\key($foo), \array_keys($arr), true)) === false ? null : $tmp;
    }

    /**
     * Inserts new array before item specified by key.
     *
     * @param array      $arr
     * @param int|string $key
     * @param array      $inserted
     */
    public function insertBefore(array &$arr, $key, array $inserted): void
    {
        $offset = (int) $this->searchKey($arr, $key);
        $arr = \array_slice($arr, 0, $offset, true) + $inserted + \array_slice($arr, $offset, null, true);
    }


    /**
     * Inserts new array after item specified by key.
     *
     * @param array      $arr
     * @param int|string $key
     * @param array      $inserted
     */
    public function insertAfter(array &$arr, $key, array $inserted): void
    {
        $offset = $this->searchKey($arr, $key);
        if ($offset === null) {
            $arr += $inserted;
        } else {
            ++$offset;
            $arr = \array_slice($arr, 0, $offset, true) + $inserted + \array_slice($arr, $offset, null, true);
        }
    }

    /**
     * Renames key in array.
     *
     * @param array      $arr
     * @param int|string $oldKey
     * @param int|string $newKey
     */
    public function renameKey(array &$arr, $oldKey, $newKey): void
    {
        $offset = $this->searchKey($arr, $oldKey);
        if ($offset !== null) {
            $keys = \array_keys($arr);
            $keys[$offset] = $newKey;
            $arr = \array_combine($keys, $arr);
        }
    }

    /**
     * Returns array entries that match the pattern.
     *
     * @param array  $arr
     * @param string $pattern
     * @param int    $flags
     *
     * @return array
     */
    public function grep(array $arr, string $pattern, int $flags = 0): array
    {
        static $messages = [
            PREG_INTERNAL_ERROR => 'Internal PCRE error',
            PREG_BACKTRACK_LIMIT_ERROR => 'pcre.backtrack_limit was exhausted',
            PREG_RECURSION_LIMIT_ERROR => 'pcre.recursion_limit was exhausted',
            PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data',
            PREG_BAD_UTF8_OFFSET_ERROR => 'Offset didn\'t correspond to the begin of a valid UTF-8 code point',
            PREG_JIT_STACKLIMIT_ERROR => 'Failed due to limited JIT stack space',
        ];

        $res = \preg_grep($pattern, $arr, $flags);

        if ($code = \preg_last_error()) {
            throw new RegexpException(($messages[$code] ?? 'Unknown error')
                . ' (pattern: ' . \implode(' or ', $arr) . ')', $code);
        }

        return $res;
    }

    /**
     * Picks element from the array by key and return its value.
     *
     * @param array       $arr
     * @param  string|int $key array key
     *
     * @return mixed
     */
    public function pick(array &$arr, $key)
    {
        if (\array_key_exists($key, $arr)) {
            $value = $arr[$key];
            unset($arr[$key]);

            return $value;
        }

        return null;
    }

    /**
     * array shift no reindex, and return key (use for assoc array)
     *
     * @param array $array
     *
     * @return array[value, key]|null
     */
    public function shift(array &$array): ?array
    {
        if ($array === []) {
            return null;
        }

        $value = \reset($array);
        $key = \key($array);

        unset($array[$key]);

        return [$value, $key];
    }
}