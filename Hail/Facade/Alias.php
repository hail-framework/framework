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
		$file = Config::get('.hail.map.alias');
		if (file_exists($file)) {
			$alias = include $file;
		} else {
			$alias = self::getConfig();
		}

		$alias += array_merge($alias, Config::get('alias'));

		return new AliasLoader($alias);
	}

	private static function getConfig()
	{
		$alias = [];
		foreach (scandir(__DIR__) as $file) {
			if (in_array($file, ['.', '..', 'Facade.php'], true)) {
				continue;
			}

			/** @var Facade $class */
			$class = '\\Hail\\Facade\\' . pathinfo($file, PATHINFO_FILENAME);
			$alias[$class::getClass()] = $class::alias();
		}

		return $alias;
	}

	public static function buildMap()
	{
		$set = self::getConfig();
		file_put_contents(
			Config::get('.hail.map.alias'),
			'<?php return ' . var_export($set, true) . ';'
		);
	}
}