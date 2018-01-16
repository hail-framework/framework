<?php
namespace Hail\Facade;

/**
 * Class Library
 *
 * @package Hail\Facade
 * @author  Feng Hao <flyinghail@msn.com>
 *
 * @method static Object get(string $key)
 * @method static bool has(string $key)
 * @method static set(string $key, $value)
 * @method static delete(string $key)
 */
class Library extends Facade
{
	protected static $name = 'lib';
}