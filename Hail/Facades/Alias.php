<?php
namespace Hail\Facades;

use Hail\AliasLoader;

/**
 * Class Alias
 *
 * @package Hail\Facades
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
		$file = Config::get('__hail.map.alias');
		if (file_exists($file)) {
			$alias = include $file;
		} else {
			$alias = self::getConfig();
		}

		return new AliasLoader($alias);
	}

	private static function getConfig()
	{
		$di = DI::getInstance();

		$alias = [
			'Debugger' => '\\Hail\\Tracy\\Debugger',
		];

		foreach ($di->keys() as $v) {
			$raw = $di->raw($v);
			if ($di->isFacade($raw)) {
				/** @var Facade $raw */
				$alias[$raw::getClass()] = $raw;
			}
		}


		return $alias;
	}

	public static function buildMap()
	{
		$set = self::getConfig();
		file_put_contents(
			Config::get('__hail.map.alias'),
			'<?php return ' . var_export($set, true) . ';'
		);
	}
}