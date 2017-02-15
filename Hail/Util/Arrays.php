<?php
namespace Hail\Util;

/**
 * Class Arrays
 *
 * @package Hail\Util
 * @author  Hao Feng <flyinghail@msn.com>
 */
class Arrays
{
	use SingletonTrait;

	/**
	 * Convert array to ArrayDot
	 *
	 * @param array $init
	 *
	 * @return ArrayDot
	 */
	public static function dot(array $init = []): ArrayDot
	{
		return new ArrayDot($init);
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
	public static function get(array $array, string $key = null, $default = null)
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
	 * Determines if an array is associative.
	 *
	 * An array is "associative" if it doesn't have sequential numerical keys beginning with zero.
	 *
	 * @param  array $array
	 *
	 * @return bool
	 */
	public static function isAssoc(array $array): bool
	{
		$keys = array_keys($array);

		return array_keys($keys) !== $keys;
	}

	/**
	 * @param array $array
	 *
	 * @return array
	 */
	public static function filter(array $array): array
	{
		return array_filter($array, [static::class, 'filterCallback']);
	}

	protected static function filterCallback($v)
	{
		return $v !== false && $v !== null;
	}
}