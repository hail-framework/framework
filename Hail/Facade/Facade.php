<?php

namespace Hail\Facade;

use Psr\Container\ContainerInterface;

/**
 * Class Facade
 *
 * @package Hail\Facade
 * @author  Hao Feng <flyinghail@msn.com>
 */
abstract class Facade
{
	/**
	 * Class alias name in container
	 *
	 * @var string
	 */
	protected static $name;

	/**
	 * Call static method
	 *
	 * @var string
	 */
	protected static $alias;

	/**
	 * The resolved object instances.
	 *
	 * @var array
	 */
	protected static $instances;

	/**
	 * @var ContainerInterface
	 */
	protected static $container;

	public static function setContainer(ContainerInterface $container)
	{
		static::$container = $container;
	}

	/**
	 * Get the root object behind the facade.
	 *
	 * @return mixed
	 */
	public static function getInstance()
	{
		if (!isset(static::$instances[static::class])) {
			static::$instances[static::class] =
				static::$container->get(static::getName());
		}

		return static::$instances[static::class];
	}

	/**
	 * Handle dynamic, static calls to the object.
	 *
	 * @param  string $method
	 * @param  array  $args
	 *
	 * @return mixed
	 */
	public static function __callStatic($method, $args)
	{
		if (null !== ($class = static::$alias)) {
			return $class::$method(...$args);
		}

		$instance = static::$instances[static::class] ?? static::getInstance();

		return $instance->$method(...$args);
	}

	public static function getName()
	{
		return static::$name ?? strtolower(substr(static::class, strrpos(static::class, '\\') + 1));
	}
}