<?php
namespace Hail\Facade;

/**
 * Class Facade
 * @package Hail\Facade
 * @author  Hao Feng <flyinghail@msn.com>
 */
abstract class Facade
{
	/**
	 * Class alias name
	 *
	 * @var string
	 */
	protected static $name = '';

	/**
	 * The resolved object instances.
	 *
	 * @var array
	 */
	protected static $instances;

	/**
	 * @var string
	 */
	protected static $alias;

	protected static $container;

	public static function setContainer($container)
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
		$name = static::class;

		if (!isset(static::$instances[$name])) {
			static::$instances[$name] = static::$container->get($name);
		}

		return static::$instances[$name];
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

	/**
	 * 由子类定义获取实例的具体实现
	 *
	 * @return object|null
	 */
	abstract protected static function instance();

	public static function getName()
	{
		if (static::$name !== '') {
			return static::$name;
		}

		return strtolower(self::getClass());
	}

	public static function getClass()
	{
		$name = static::class;

		return substr($name, strrpos($name, '\\') + 1);
	}

	public static function inDI()
	{
		return static::$inDI;
	}

	public static function alias()
	{
		return static::$alias ?? static::class;
	}
}