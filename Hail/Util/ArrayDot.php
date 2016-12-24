<?php
namespace Hail\Util;

/**
 * Class ArrayDot
 *
 * @package Hail\Util
 * @author  Hao Feng <flyinghail@msn.com>
 */
class ArrayDot implements \ArrayAccess, \Countable, \Iterator
{
	use ArrayTrait;

	protected $items = [];
	protected $cache = [];

	/**
	 * DotArray constructor.
	 *
	 * @param array $init
	 */
	public function __construct(array $init = [])
	{
		$this->init($init);
	}

	/**
	 * @inheritdoc
	 */
	public function current()
	{
		return current($this->items);
	}

	/**
	 * @inheritdoc
	 */
	public function next()
	{
		next($this->items);
	}

	/**
	 * @inheritdoc
	 */
	public function key()
	{
		return key($this->items);
	}

	/**
	 * @inheritdoc
	 */
	public function valid()
	{
		return key($this->items) !== null;
	}

	/**
	 * @inheritdoc
	 */
	public function rewind()
	{
		reset($this->items);
	}

	/**
	 * @inheritdoc
	 */
	public function count(): int
	{
		return count($this->items);
	}

	/**
	 * Get an item from an array using "dot" notation.
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function get(string $key = null)
	{
		if ($key === null) {
			return $this->items;
		} elseif (isset($this->items[$key])) {
			return $this->items[$key];
		} elseif (isset($this->cache[$key])) {
			return $this->cache[$key];
		}

		$array = $this->items;
		foreach (explode('.', $key) as $segment) {
			if (is_array($array) && isset($array[$segment])) {
				$array = $array[$segment];
			} else {
				return null;
			}
		}

		return $this->cache[$key] = $array;
	}

	/**
	 * Check if an item or items exist in an array using "dot" notation.
	 *
	 * @param  string[] ...$keys
	 *
	 * @return bool
	 */
	public function has(string ...$keys): bool
	{
		$array = $this->items;
		if (!$array || $keys === []) {
			return false;
		}

		foreach ($keys as $key) {
			if (isset($this->cache[$key]) || isset($array[$key])) {
				continue;
			}

			$subKeyArray = $array;
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
	 * Set an array item to a given value using "dot" notation.
	 *
	 * If no key is given to the method, the entire array will be replaced.
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 *
	 * @return mixed
	 */
	public function set(string $key, $value)
	{
		$array = &$this->items;
		foreach (explode('.', $key) as $k) {
			if (!isset($array[$k]) || !is_array($array[$k])) {
				$array[$k] = [];
			}
			$array = &$array[$key];
		}

		if (is_array($value)) {
			$this->cache = array_merge(
				$this->cache,
				self::dot($value, $key . '.')
			);
		} elseif ($value !== null) {
			$this->cache[$key] = $value;
		}

		return ($array = $value);
	}

	/**
	 * @param array $array
	 *
	 * @return array
	 */
	public function init(array $array): array
	{
		$this->items = $array;
		$this->cache = $array === [] ? [] : self::dot($array);

		return $array;
	}

	/**
	 * Flatten a multi-dimensional associative array with dots.
	 *
	 * @param  array  $array
	 * @param  string $prepend
	 *
	 * @return array
	 */
	public static function dot(array $array, string $prepend = ''): array
	{
		$results = [
			0 => [],
		];

		foreach ($array as $key => $value) {
			if (is_array($value) && !empty($value)) {
				$results[] = self::dot($value, $prepend . $key . '.');
			} else {
				$results[0][$prepend . $key] = $value;
			}
		}

		return call_user_func_array('array_merge', $results);
	}

	/**
	 * Remove one or many array items from a given array using "dot" notation.
	 *
	 * @param  string[] ...$keys
	 *
	 * @return void
	 */
	public function delete(string ...$keys)
	{
		$array = &$this->items;

		if ($keys === []) {
			return;
		}

		$original = &$array;
		foreach ($keys as $key) {
			$this->clearCache($key);

			// if the exact key exists in the top-level, remove it
			if (isset($array[$key])) {
				unset($array[$key]);
				continue;
			}

			// clean up before each pass
			$array = &$original;

			$parts = explode('.', $key);
			$delKey = array_pop($parts);
			foreach ($parts as $part) {
				if (isset($array[$part]) && is_array($array[$part])) {
					$array = &$array[$part];
				} else {
					continue 2;
				}
			}

			unset($array[$delKey]);
		}
	}

	private function clearCache(string $key)
	{
		unset($this->cache[$key]);
		foreach ($this->cache as $k => $v) {
			if (strpos($k, $key) === 0) {
				unset($this->cache[$k]);
			}
		}
	}
}