<?php
namespace Hail\Util;

/**
 * Class Model
 *
 * @package Hail
 * @author  Hao Feng <flyinghail@msn.com>
 */
class ObjectFactory implements \ArrayAccess
{
	use ArrayTrait;

	private $namespace;

	public function __construct($namespace)
	{
		$this->namespace = trim($namespace, '\\') . '\\';
	}

	public function __call($name, $arguments)
	{
		return $this->$name ?? $this->get($name);
	}

	public function has($key)
	{
		return isset($this->$key);
	}

	public function get($key)
	{
		if (!isset($this->$key)) {
			$class = $this->namespace . ucfirst($key);
			if (method_exists($class, 'getInstance')) {
				return $this->set($key, $class::getInstance());
			} elseif (!class_exists($class)) {
				throw new \LogicException("Class $class Not Defined");
			}

			return $this->set($key, new $class());
		}

		return $this->$key;
	}

	public function set($key, $value)
	{
		$class = $this->namespace . ucfirst($key);
		if (!$value instanceof $class) {
			throw new \LogicException("Object Not Instance of $class");
		}

		return $this->$key = $value;
	}

	public function delete($key)
	{
		if (isset($this->$key)) {
			unset($this->$key);
		}
	}
}