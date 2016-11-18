<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2016/11/18 0018
 * Time: 14:15
 */

namespace Hail\Utils;

/**
 * Class ArrayTrait
 *
 * @package Hail\Utils
 */
trait ArrayTrait
{
	public function offsetExists($offset)
	{
		return $this->has($offset);
	}

	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	public function offsetSet($offset, $value)
	{
		$this->set($offset, $value);
	}

	public function offsetUnset($offset)
	{
		$this->delete($offset);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	abstract public function set($key, $value);

	/**
	 * @param  string $key
	 * @param  mixed $default
	 *
	 * @return mixed
	 */
	abstract public function get($key, $default = null);

	/**
	 * @param string $key
	 */
	abstract public function delete($key);

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has(string $key) : bool
	{
		return $this->get($key) !== null;
	}

	/**
	 * @param array $array
	 */
	public function setMultiple(array $array)
	{
		foreach ($array as $k => $v) {
			$this->set($k, $v);
		}
	}

	/**
	 * @param array $keys
	 *
	 * @return array
	 */
	public function getMultiple(array $keys)
	{
		return array_map([$this, 'get'], $keys);
	}
}