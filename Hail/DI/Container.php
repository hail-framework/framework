<?php

namespace Hail\DI;

use Hail\Facades\Facade;
use Psr\Container\ContainerInterface;

/**
 * Class DI
 * @package Hail
 */
class Container implements \ArrayAccess, ContainerInterface
{
	private $values = [];
	private $raw = [];

	/**
	 * Instantiate the container.
	 *
	 * Objects and parameters can be passed as argument to the constructor.
	 *
	 * @param array $values The parameters or objects.
	 */
	public function __construct(array $values = array())
	{
		foreach ($values as $key => $value) {
			$this->set($key, $value);
		}
	}

	public function set($id, $value)
	{
		if ($id === 'di' || isset($this->raw[$id])) {
			throw new Exception\Container(sprintf('Cannot override frozen service "%s".', $id));
		}

		$this->values[$id] = $value;
	}

	/**
	 * Gets a parameter or an object.
	 *
	 * @param string $id The unique identifier for the parameter or object
	 *
	 * @return mixed The value of the parameter or an object
	 *
	 * @throws Exception\NotFound if the identifier is not defined
	 */
	public function get($id)
	{
		if ($id === 'di') {
			return $this;
		}

		if (!isset($this->values[$id])) {
			throw new Exception\NotFound(sprintf('Identifier "%s" is not defined.', $id));
		}

		if (isset($this->raw[$id])) {
			return $this->values[$id];
		}

		$val = $raw = $this->values[$id];
		if ($this->isFacade($raw)) {
			/** @var Facade $raw */
			$val = $raw::getInstance();
		} else if ($raw instanceof \Closure) {
			$val = $raw($this);
		} elseif (is_callable($raw, true, $call)) {
			$val = $call($this);
		}

		$this->raw[$id] = $raw;

		return $this->values[$id] = $val;
	}

	public function isFacade($raw)
	{
		return is_string($raw) &&
			strpos($raw, '\\Hail\\Facades\\') === 0 &&
			class_exists($raw);
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($id)
	{
		return $id === 'di' || isset($this->values[$id]);
	}

	/**
	 * Unsets a parameter or an object.
	 *
	 * @param string $id The unique identifier for the parameter or object
	 */
	public function remove($id)
	{
		if (isset($this->values[$id])) {
			unset($this->values[$id], $this->raw[$id]);
		}
	}

	/**
	 * Gets a parameter or the closure defining an object.
	 *
	 * @param string $id The unique identifier for the parameter or object
	 *
	 * @return mixed The value of the parameter or the closure defining an object
	 *
	 * @throws \InvalidArgumentException if the identifier is not defined
	 */
	public function raw($id)
	{
		if ($id === 'di') {
			return '\\Hail\\Facades\\DI';
		} elseif (!isset($this->values[$id])) {
			throw new Exception\NotFound(sprintf('Identifier "%s" is not defined.', $id));
		}

		if (isset($this->raw[$id])) {
			return $this->raw[$id];
		}

		return $this->values[$id];
	}

	/**
	 * Returns all defined value names.
	 *
	 * @return array An array of value names
	 */
	public function keys()
	{
		$keys = array_keys($this->values);
		$keys[] = 'di';
		return $keys;
	}

	public function offsetSet($id, $value)
	{
		$this->set($id, $value);
	}


	public function offsetGet($id)
	{
		return $this->get($id);
	}

	public function offsetExists($id)
	{
		return $this->has($id);
	}


	public function offsetUnset($id)
	{
		$this->remove($id);
	}

	/**
	 * @param $func
	 * @param $args
	 *
	 * @return mixed
	 */
	public function __call($func, $args)
	{
		return $this->get($func);
	}

	/**
	 * @param string $id
	 *
	 * @return mixed
	 */
	public function __get($id)
	{
		return $this->get($id);
	}
}