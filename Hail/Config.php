<?php
namespace Hail;

use Hail\Facades\Arrays;
use Hail\Utils\{
	ArrayDot,
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
	 * @var ArrayDot
	 */
	protected $items = [];

	public function __construct()
	{
		$this->items = Arrays::dot();
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set($key, $value)
	{
		// 框架内置 config 不允许修改
		if ($key[0] === '.') {
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
	 * 优先 {SYSTEM_PATH}/config/{$space}.php，其次 {HAIL_PATH}/config/{$space}.php
	 * $space 为 . 开头，只读取 {HAIL_PATH}/config/{$space}.php
	 *
	 * @param string $space
	 *
	 * @return array|mixed|null
	 */
	protected function load($space)
	{
		$file = $this->file($space);

		$baseFile = HAIL_PATH . $file;
		$sysFile = SYSTEM_PATH . $file;

		$check = [$sysFile, $baseFile];

		if (($config = $this->optimizeGet($space, $check)) !== false) {
			return $config;
		}

		$config = [];

		if (
			SYSTEM_PATH !== HAIL_PATH &&
			$space[0] !== '.' &&
			file_exists($sysFile)
		) {
			$config = require $sysFile;
		}

		if (file_exists($baseFile)) {
			$config += require $baseFile;
		}

		$this->optimizeSet($space, $config, $check);

		return $config;
	}

	protected function file($space)
	{
		return 'config/' . $space . '.php';
	}
}