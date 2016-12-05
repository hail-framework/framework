<?php
namespace Hail;

use Hail\Facades\Arrays;
use Hail\Utils\{
	ArrayTrait,
	OptimizeTrait
};

/**
 * Class Php
 *
 * @package Hail\Config
 */
class Config implements \ArrayAccess
{
	use OptimizeTrait;
	use ArrayTrait;

	/**
	 * All of the configuration items.
	 *
	 * @var array
	 */
	protected $items = [];

	public function __construct()
	{
		$this->items = Arrays::dot([]);
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set($key, $value)
	{
		// 框架内置 config 不允许修改
		if (strpos($key, '__') === 0) {
			return;
		}

		$this->items[$key] = $value;
	}

	/**
	 * Get the specified configuration value.
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function get($key)
	{
		if (isset($this->items[$key])) {
			return $this->items[$key];
		}

		$space = explode('.', $key)[0];
		if (isset($this->items[$space])) {
			return null;
		}

		$this->items[$space] = $this->load($space);
		return $this->items[$key] ?? null;
	}

	public function delete($key)
	{
		unset($this->items[$key]);
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
		$file = $this->file($space);

		$config = $this->optimizeGet($space, [
			SYSTEM_PATH . $file,
			HAIL_PATH . $file,
		]);

		if ($config !== false) {
			return $config;
		}

		return $this->readFile($space);
	}

	/**
	 * 优先 {SYSTEM_PATH}/config/{$space}.php，其次 {HAIL_PATH}/config/{$space}.php
	 * $space 为 __ 开头，只读取 {HAIL_PATH}/config/{$space}.php
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

		return $array;
	}

	protected function file($space)
	{
		return 'config/' . $space . '.php';
	}
}