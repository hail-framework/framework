<?php
namespace Hail\Facades;

use Hail\AliasLoader;

class Alias extends Facade
{
	protected static $mapFile = TEMP_PATH . 'runtime/aliasMap.php';

	protected static function instance()
	{
		if (file_exists(self::$mapFile)) {
			$alias = include self::$mapFile;
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
		file_put_contents(self::$mapFile, '<?php return ' . var_export($set, true) . ';');
	}
}