<?php

namespace Hail\DI;

use ArrayAccess;
use Hail\DI\Exception\{
	ContainerException,
	NotFoundException
};
use Hail\Facades\Facade;
use Hail\Utils\ArrayTrait;
use Psr\Container\ContainerInterface;

/**
 * Simple Container
 *
 * @package Hail\DI
 */
class Container implements ArrayAccess, ContainerInterface
{
	use ArrayTrait;

	private $values = [];
	private $raw = [];

	/**
	 * Instantiate the container.
	 *
	 * Objects and parameters can be passed as argument to the constructor.
	 *
	 * @param array $values The parameters or objects.
	 *
	 * @throws ContainerException
	 */
	public function __construct(array $values = [])
	{
		foreach ($values as $key => $value) {
			$this->set($key, $value);
		}
	}

	/**
	 * @param $id
	 * @param $value
	 *
	 * @throws ContainerException
	 */
	public function set($id, $value)
	{
		if ($id === 'di' || isset($this->raw[$id])) {
			throw new ContainerException(sprintf('Cannot override frozen service "%s".', $id));
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
	 * @throws NotFoundException if the identifier is not defined
	 */
	public function get($id)
	{
		if ($id === 'di') {
			return $this;
		}

		if (!isset($this->values[$id])) {
			throw new NotFoundException(sprintf('Identifier "%s" is not defined.', $id));
		}

		if (isset($this->raw[$id])) {
			return $this->values[$id];
		}

		$val = $raw = $this->values[$id];
		if ($this->isFacade($raw)) {
			/** @var Facade $raw */
			$val = $raw::getInstance();
		} elseif ($raw instanceof \Closure) {
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

	public function has($id)
	{
		return $id === 'di' || isset($this->values[$id]);
	}

	/**
	 * Unsets a parameter or an object.
	 *
	 * @param string $id The unique identifier for the parameter or object
	 */
	public function delete($id)
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
	 * @throws NotFoundException if the identifier is not defined
	 */
	public function raw($id)
	{
		if ($id === 'di') {
			return '\\Hail\\Facades\\DI';
		} elseif (!isset($this->values[$id])) {
			throw new NotFoundException(sprintf('Identifier "%s" is not defined.', $id));
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

	public function __call($name, $arguments)
	{
		return $this->get($name);
	}
}