<?php

namespace Hail\Container;

use Hail\Container\Exception\{
	ContainerException, NotFoundException
};
use Hail\Facade\{
	Facade,
	DI
};
use Hail\Util\ArrayTrait;
use Psr\Container\ContainerInterface;

/**
 * Simple Container
 *
 * @package Hail\Container
 */
class Container implements \ArrayAccess, ContainerInterface
{
	use ArrayTrait;

	private $values = [];
	private $raw = [];

	/**
	 * @param array $values .
	 */
	public function __construct(array $values = [])
	{
		foreach ($values as $key => $value) {
			$this->values[$key] = $value;
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
			throw new ContainerException('Cannot override frozen service "' . $id . '".');
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
		} elseif (!isset($this->values[$id])) {
			throw new NotFoundException('Identifier "' . $id . '" is not defined.');
		}

		if (isset($this->raw[$id])) {
			return $this->values[$id];
		}

		$val = $raw = $this->values[$id];
		if (is_string($raw) &&
			strpos($raw, '\\Hail\\Facade\\') === 0 &&
			class_exists($raw)
		) {
			/** @var Facade $raw */
			$val = $raw::getInstance();
		} elseif ($raw instanceof \Closure) {
			$val = $raw($this);
		}

		$this->raw[$id] = $raw;

		return $this->values[$id] = $val;
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
		unset($this->values[$id], $this->raw[$id]);
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
			return '\\' . DI::class;
		} elseif (!isset($this->values[$id])) {
			throw new NotFoundException('Identifier "' . $id . '" is not defined.');
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