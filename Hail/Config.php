<?php
namespace Hail;

use Hail\Utils\OptimizeTrait;

/**
 * Class Php
 *
 * @package Hail\Config
 */
class Config implements \ArrayAccess
{
	use OptimizeTrait;

	/**
	 * @{inheritDoc}
	 */
	public function offsetExists($offset)
	{
		$val = $this->get($offset);

		return $val !== null;
	}

	/**
	 * @{inheritDoc}
	 */
	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	/**
	 * @{inheritDoc}
	 */
	public function offsetSet($offset, $value)
	{
		$this->set($offset, $value);
	}

	/**
	 * @{inheritDoc}
	 */
	public function offsetUnset($name)
	{
		if (strpos($name, '.') === false) {
			unset($this->items[$name]);
		} else {
			$key = explode('.', $name);
			$array = &$this->items;
			foreach ($key as $v) {
				$array = &$array[$v];
			}
			$array = null;
		}
	}

	/**
	 * All of the configuration items.
	 *
	 * @var array
	 */
	protected $items = [];

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
	{
		// 框架内置 config 不允许修改
		if (strpos($key, '__') === 0) {
			return;
		} elseif (strpos($key, '.') === false) {
			$this->items[$key] = $value;
		} else {
			$key = explode('.', $key);
			$array = &$this->items;
			foreach ($key as $v) {
				$array = &$array[$v];
			}
			$array = $value;
		}
	}

	/**
	 * Get the specified configuration value.
	 *
	 * @param  string $key
	 * @param  mixed $default
	 *
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		if (isset($this->items[$key])) {
			return $this->items[$key];
		}

		if (strpos($key, '.') === false) {
			return $this->load($key);
		}

		$split = explode('.', $key);
		$array = $this->load(
			array_shift($split)
		);

		return $this->arrayGet($array, $split, $default);
	}

	/**
	 * Read config array from cache or file
	 *
	 * @param string $space
	 *
	 * @return array|mixed|null
	 */
	protected function load($space)
	{
		if (isset($this->items[$space])) {
			return $this->items[$space];
		}

		$file = $this->file($space);

		$config = $this->optimizeGet($space, [
			SYSTEM_PATH . $file,
			HAIL_PATH . $file,
		]);

		if ($config !== false) {
			return $this->items[$space] = $config;
		}

		return $this->readFile($space);
	}

	/**
	 * 优先 {SYSTEM_PATH}/config/{$space}.php，其次 {HAIL_PATH}/config/{$space}.php
	 * $space 为 __ 开头，则只读取 {HAIL_PATH}/config/{$space}.php
	 *
	 * @param string $space
	 *
	 * @return null|string
	 */
	protected function readFile($space)
	{
		$file = $this->file($space);
		$base = null;
		if (file_exists(HAIL_PATH . $file)) {
			$base = require HAIL_PATH . $file;
		}

		if (
			SYSTEM_PATH !== HAIL_PATH &&
			strpos($space, '__') !== 0 &&
			file_exists(SYSTEM_PATH . $file)
		) {
			$array = require SYSTEM_PATH . $file;
			if ($base !== null) {
				$array = array_merge($base, $array);
			}
		} elseif ($base === null) {
			return null;
		} else {
			$array = $base;
		}

		$this->optimizeSet($space, $array, [
			SYSTEM_PATH . $file,
			HAIL_PATH . $file,
		]);

		return $this->items[$space] = $array;
	}

	protected function arrayGet($array, $key, $default)
	{
		foreach ($key as $segment) {
			if (!is_array($array) || !isset($array[$segment])) {
				return $default;
			}

			$array = $array[$segment];
		}

		return $array;
	}

	protected function file($space)
	{
		return 'config/' . $space . '.php';
	}
}