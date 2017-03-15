<?php
namespace Hail\Util;

/**
 * Class ArrayTrait
 *
 * @package Hail\Util
 * @author Hao Feng <flyinghail@msn.com>
 */
trait ArrayTrait
{
	public function __isset($name)
	{
		return $this->has($name);
	}

	public function __set($name, $value)
	{
		$this->set($name, $value);
	}

	public function __get($name)
	{
		return $this->get($name);
	}

	public function __unset($name)
	{
		$this->delete($name);
	}

	public function offsetExists($offset)
	{
		return $this->has($offset);
	}

	public function offsetSet($offset, $value)
	{
		$this->set($offset, $value);
	}

	public function offsetGet($offset)
	{
		return $this->get($offset);
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
	 *
	 * @return mixed
	 */
	abstract public function get($key);

	/**
	 * @param string $key
	 */
	abstract public function delete($key);

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has($key)
	{
		return $this->get($key) !== null;
	}

	/**
	 * @param iterable $array
	 */
	public function setMultiple($array)
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
		return array_combine(
			$keys,
			array_map([$this, 'get'], $keys)
		);
	}
}