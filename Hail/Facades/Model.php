<?php
namespace Hail\Facades;

use Hail\Utils\ObjectFactory;

/**
 * Class Model
 *
 * @package Hail\Facades
 * @author  Hao Feng <flyinghail@msn.com>
 *
 * @method static Object get(string $key)
 * @method static bool has(string $key)
 * @method static set(string $key, $value)
 * @method static delete(string $key)
 */
class Model extends Facade
{
	protected static function instance()
	{
		return new ObjectFactory('App\\Model');
	}
}