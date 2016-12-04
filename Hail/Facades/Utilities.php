<?php
namespace Hail\Facades;

use Hail\Utils\ObjectFactory;

/**
 * Class Utilities
 *
 * @package Hail\Facades
 * @author  Hao Feng <flyinghail@msn.com>
 *
 * @method static Object get(string $key)
 * @method static bool has(string $key)
 * @method static set(string $key, $value)
 * @method static delete(string $key)
 */
class Utilities extends Facade
{
	protected static $name = 'utils';

	protected static function instance()
	{
		return new ObjectFactory('Hail\\Utils');
	}
}