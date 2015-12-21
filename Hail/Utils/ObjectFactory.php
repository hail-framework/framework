<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2015/12/16 0016
 * Time: 15:07
 */

namespace Hail\Utils;

/**
 * Class Model
 * @package Hail
 */
class ObjectFactory implements \ArrayAccess
{
	private $namespace;
	private $object;

	public function __construct($name)
	{
		$this->namespace = 'App\\' . $name . '\\';
	}

	public function __get($name)
	{
		return $this->offsetGet($name);
	}

	public function __call($name, $arguments)
	{
		return $this->offsetGet($name);
	}

	public function __callStatic($name, $arguments)
	{
		return $this->offsetGet($name);
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetExists($name)
	{
		if (!isset($this->object[$name])) {
			return class_exists($this->namespace . ucfirst($name));
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetGet($name)
	{
		if (!isset($this->object[$name])) {
			$class = $this->namespace . ucfirst($name);
			if (!class_exists($class)) {
				throw new \RuntimeException("Model $name Not Defined");
			}

			return $this->object[$name] = new $class();
		}

		return $this->object[$name];
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetSet($name, $value)
	{
		if (isset($this->object[$name])) {
			return $this->object[$name];
		}

		$class = $this->namespace . ucfirst($name);
		if (!$value instanceof $class) {
			throw new \RuntimeException("Object Not Instance of $class");
		}

		$this->object[$name] = $value;
		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetUnset($name)
	{
		if (isset($this->object[$name])) {
			unset($this->object[$name]);
		}
	}
}