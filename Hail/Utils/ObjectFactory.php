<?php
namespace Hail\Utils;

use Hail\Exception\InvalidStateException;

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
		if (!isset($this->$key)) {
			return class_exists($this->namespace . ucfirst($key));
		}

		return true;
	}

	public function get($key)
	{
		if (!isset($this->$key)) {
			$class = $this->namespace . ucfirst($key);
			if (method_exists($class, 'getInstance')) {
				return $this->$key = $class::getInstance();
			} elseif (!class_exists($class)) {
				throw new InvalidStateException("Class $class Not Defined");
			}

			return $this->$key = new $class();
		}

		return $this->$key;
	}

	public function set($key, $value)
	{
		if (isset($this->$key)) {
			throw new InvalidStateException(sprintf('Cannot override frozen object "' . $key . '".'));
		}

		$class = $this->namespace . ucfirst($key);
		if (!$value instanceof $class) {
			throw new InvalidStateException("Object Not Instance of $class");
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