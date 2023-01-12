<?php
namespace Hail\Util;

/**
 * Class ArrayTrait
 *
 * @package Hail\Util
 * @author Feng Hao <flyinghail@msn.com>
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

	#[\ReturnTypeWillChange]
	public function offsetExists($offset)
	{
		return $this->has($offset);
	}

	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value)
	{
		$this->set($offset, $value);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset($offset)
	{
		$this->delete($offset);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
    {
        throw new \LogicException(__CLASS__ . '::set method not defined');
    }

	/**
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function get($key)
    {
        throw new \LogicException(__CLASS__ . '::get method not defined');
    }

	/**
	 * @param string $key
	 */
	public function delete($key)
    {
        throw new \LogicException(__CLASS__ . '::delete method not defined');
    }

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has($key): bool
	{
		return $this->get($key) !== null;
	}

	/**
	 * @param iterable $array
	 */
	public function setMultiple($array): void
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
	public function getMultiple(array $keys): array
	{
		return \array_combine(
			$keys,
			\array_map([$this, 'get'], $keys)
		);
	}
}