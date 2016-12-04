<?php
/**
 * @from https://github.com/laravel/framework/blob/5.1/src/Illuminate/Support/Facades/Facade.php
 * Copyright (c) <Taylor Otwell>
 */

namespace Hail\Facades;

use Hail\Exception\InvalidStateException;

/**
 * Class Facade
 * @package Hail\Facades
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
	protected static $resolvedInstance;

	/**
	 * @var bool
	 */
	protected static $inDI = true;

	/**
	 * Get the root object behind the facade.
	 *
	 * @return mixed
	 */
	public static function getInstance()
	{
		$name = static::class;
		if (!isset(static::$resolvedInstance[$name])) {
			return static::$resolvedInstance[$name] = static::instance();
		}

		return static::$resolvedInstance[$name];
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
		$instance = static::$resolvedInstance[static::class] ?? static::getInstance();
		switch (count($args)) {
			case 0:
				return $instance->$method();
			case 1:
				return $instance->$method($args[0]);
			case 2:
				return $instance->$method($args[0], $args[1]);
			case 3:
				return $instance->$method($args[0], $args[1], $args[2]);
			case 4:
				return $instance->$method($args[0], $args[1], $args[2], $args[3]);
			default:
				return call_user_func_array([$instance, $method], $args);
		}
	}

	/**
	 * 由子类定义获取实例的具体实现
	 *
	 * @return object
	 * @throws \Hail\Exception\InvalidStateException
	 */
	protected static function instance()
	{
		throw new InvalidStateException('Class should define instance() method');
	}

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
		return self::$inDI;
	}
}