<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2016/11/18 0018
 * Time: 12:58
 */

namespace Hail\Utils;

class Arrays
{
	/**
	 * Get an item from an array using "dot" notation.
	 *
	 * @param  array $array
	 * @param  string $key
	 * @param  mixed $default
	 *
	 * @return mixed
	 */
	public static function get(array $array, string $key, $default = null)
	{
		if ($key === null) {
			return $array;
		}

		if (isset($array[$key])) {
			return $array[$key];
		}

		foreach (explode('.', $key) as $segment) {
			if (is_array($array) && isset($array[$segment])) {
				$array = $array[$segment];
			} else {
				return $default;
			}
		}

		return $array;
	}

	/**
	 * Check if an item or items exist in an array using "dot" notation.
	 *
	 * @param  array $array
	 * @param  string|array $keys
	 *
	 * @return bool
	 */
	public static function has(array $array, $keys)
	{
		if ($keys === null) {
			return false;
		}

		$keys = (array) $keys;
		if (!$array || $keys === []) {
			return false;
		}

		foreach ($keys as $key) {
			$subKeyArray = $array;
			if (isset($array[$key])) {
				continue;
			}

			foreach (explode('.', $key) as $segment) {
				if (is_array($subKeyArray) && isset($subKeyArray[$segment])) {
					$subKeyArray = $subKeyArray[$segment];
				} else {
					return false;
				}
			}
		}

		return true;
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
	public static function isAssoc(array $array)
	{
		$keys = array_keys($array);

		return array_keys($keys) !== $keys;
	}

	/**
	 * Set an array item to a given value using "dot" notation.
	 *
	 * If no key is given to the method, the entire array will be replaced.
	 *
	 * @param  array $array
	 * @param  string $key
	 * @param  mixed $value
	 *
	 * @return array
	 */
	public static function set(array &$array, string $key, $value)
	{
		if ($key !== null) {
			foreach (explode('.', $key) as $k) {
				if (!isset($array[$k]) || !is_array($array[$k])) {
					$array[$k] = [];
				}
				$array = &$array[$key];
			}
		}

		return $array = $value;
	}

	/**
	 * Flatten a multi-dimensional associative array with dots.
	 *
	 * @param  array $array
	 * @param  string $prepend
	 *
	 * @return array
	 */
	public static function dot(array $array, string $prepend = '')
	{
		$results = [
			0 => [],
		];

		foreach ($array as $key => $value) {
			if (is_array($value) && !empty($value)) {
				$results[] = static::dot($value, $prepend . $key . '.');
			} else {
				$results[0][$prepend . $key] = $value;
			}
		}

		return call_user_func_array('array_merge', $results);
	}

	/**
	 * Remove one or many array items from a given array using "dot" notation.
	 *
	 * @param  array $array
	 * @param  array|string $keys
	 *
	 * @return void
	 */
	public static function delete(array &$array, $keys)
	{
		$original = &$array;
		$keys = (array) $keys;
		if ($keys === []) {
			return;
		}

		foreach ($keys as $key) {
			// if the exact key exists in the top-level, remove it
			if (isset($array[$key])) {
				unset($array[$key]);
				continue;
			}

			$parts = explode('.', $key);
			// clean up before each pass
			$array = &$original;
			while (count($parts) > 1) {
				$part = array_shift($parts);
				if (isset($array[$part]) && is_array($array[$part])) {
					$array = &$array[$part];
				} else {
					continue 2;
				}
			}
			unset($array[array_shift($parts)]);
		}
	}
}