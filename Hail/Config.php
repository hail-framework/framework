<?php
namespace Hail;

use Hail\Cache\EmbeddedTrait;

/**
 * Class Php
 * @package Hail\Config
 */
class Config implements \ArrayAccess
{
	use EmbeddedTrait;

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

	public function set($key, $value)
	{
		$v = $this->get($key);
		if ($v === $value) {
			return;
		}

		if (strpos($key, '.') === false) {
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

		$key = explode('.', $key);
		$array = $this->load(
			array_shift($key)
		);
		return $this->arrayGet($array, $key, $default);
	}

	/**
	 * Read config array from cache or file
	 *
	 * @param string $space
	 * @return array|mixed|null
	 */
	public function load($space)
	{
		if (isset($this->items[$space])) {
			return $this->items[$space];
		}

		if ($this->updateCheck($space, $this->file($space))) {
			$config = $this->getCache($space);
			if (is_array($config)) {
				return $this->items[$space] = $config;
			}
		}

		return $this->readFile($space);
	}

	/**
	 * @param string $space
	 * @return null|string
	 */
	protected function readFile($space)
	{
		$file = $this->file($space);
		if (!file_exists($file)) {
			return null;
		}

		$array = require $file;
		$this->setCache($space, $array);
		$this->setTime($space, $file);

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

	public function file($space)
	{
		return SYSTEM_PATH . 'config/' . $space . '.php';
	}
}