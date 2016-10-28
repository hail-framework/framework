<?php
namespace Hail\Facades;

use Hail\DI\Container;

class DI extends Facade
{
	protected static $mapFile = TEMP_PATH . 'runtime/diMap.php';

	protected static function instance()
	{
		if (file_exists(self::$mapFile)) {
			$set = include self::$mapFile;
		} else {
			$set = self::getConfig();
		}

		return new Container($set);
	}

	private static function getConfig()
	{
		$set = [];
		foreach (scandir(__DIR__) as $file) {
			if (in_array($file, ['.', '..', 'Facade.php', 'DI.php'], true)) {
				continue;
			}

			/** @var Facade $class */
			$class = '\\Hail\\Facades\\' . pathinfo($file, PATHINFO_FILENAME);
			$set[$class::getName()] = $class;
		}

		return $set;
	}

	public static function buildMap()
	{
		$set = self::getConfig();
		file_put_contents(self::$mapFile, '<?php return ' . var_export($set, true) . ';');
	}
}