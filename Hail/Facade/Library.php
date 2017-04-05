<?php
namespace Hail\Facade;

use Hail\Util\ObjectFactory;

/**
 * Class Library
 *
 * @package Hail\Facade
 * @author  Hao Feng <flyinghail@msn.com>
 *
 * @method static Object get(string $key)
 * @method static bool has(string $key)
 * @method static set(string $key, $value)
 * @method static delete(string $key)
 */
class Library extends Facade
{
	protected static $name = 'lib';

	protected static function instance()
	{
		return new ObjectFactory('App\\Library');
	}
}