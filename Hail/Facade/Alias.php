<?php
namespace Hail\Facade;

use Hail\AliasLoader;

/**
 * Class Alias
 *
 * @package Hail\Facade
 *
 * @method static void alias(string $class, string $alias)
 * @method static array getAliases()
 * @method static void setAliases(array $aliases)
 * @method static void register()
 */
class Alias extends Facade
{
	protected static function instance()
	{
		return new AliasLoader(
			Config::get('alias')
		);
	}
}