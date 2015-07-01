<?php
namespace Hail\Config;

use Hail\Cache\EmbeddedTrait;

/**
 * Class Php
 * @package Hail\Config
 */
class Php
{
	use EmbeddedTrait;

	/**
	 * All of the configuration items.
	 *
	 * @var array
	 */
	protected $items = [];

	public function __construct($di)
	{
		$this->initCache($di);
	}

	/**
	 * Determine if the given configuration value exists.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function has($key)
	{
		if (empty($this->items)) return false;
		if (isset($this->items[$key])) return true;

		$return = $this->array_get($this->items, $key, null);
		return (null === $return) ? false : true;
	}


	/**
	 * Get the specified configuration value.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
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
		return $this->array_get($array, $key, $default);
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

		if ($this->updateCheck($space, CONFIG_PATH . $space . '.php')) {
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
		$file = CONFIG_PATH . $space . '.php';
		if (!file_exists($file)) {
			return null;
		}

		$array = require $file;
		$this->setCache($space, $array);
		$this->setTime($space, $file);

		return $this->items[$space] = $array;
	}

	protected function array_get($array, $key, $default)
	{
		foreach ($key as $segment) {
			if (!is_array($array) || !isset($array[$segment])) {
				return $default;
			}
			$array = $array[$segment];
		}
		return $array;
	}
}