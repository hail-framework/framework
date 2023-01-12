<?php
namespace Hail\Util;

use Hail\Container\Container;

/**
 * Class Model
 *
 * @package Hail
 * @author  Feng Hao <flyinghail@msn.com>
 */
class ObjectFactory implements \ArrayAccess
{
	use ArrayTrait;

	private $namespace;

    /**
     * @var Container
     */
	private $container;

    private $objects;

	public function __construct($namespace, Container $container = null)
	{
		$this->namespace = '\\' . \trim($namespace, '\\') . '\\';
		$this->container = $container;
	}

	public function __call($name, $arguments)
	{
		return $this->$name ?? $this->get($name);
	}

	public function has($key)
	{
		return isset($this->objects[$key]);
	}

	public function get($key)
	{
		if (!isset($this->objects[$key])) {
			$class = $this->namespace . \ucfirst($key);

			if ($this->container) {
			    $object = $this->container->build($class);
            } else {
			    $object = new $class;
            }

            return $this->set($key, $object);

		}

		return $this->objects[$key];
	}

	public function set($key, $value)
	{
		$class = $this->namespace . \ucfirst($key);
		if (!$value instanceof $class) {
			throw new \LogicException("Object Not Instance of $class");
		}

		return $this->objects[$key] = $value;
	}

	public function delete($key)
	{
		if (isset($this->objects[$key])) {
			unset($this->objects[$key]);
		}
	}
}