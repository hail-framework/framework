<?php
namespace Hail\Facades;

use Hail\DI\Container;

/**
 * Class DI
 *
 * @package Hail\Facades
 *
 * @method static void set(string $id, mixed $value)
 * @method static mixed get(string $id)
 * @method static bool has(string $id)
 * @method static void remove(string $id)
 * @method static mixed raw(string $id)
 * @method static array keys()
 */
class DI extends Facade
{
	protected static function instance()
	{
		$file = Config::get('__hail.map.di');
		if (file_exists($file)) {
			$set = include $file;
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
			if ($class::inDI()) {
				$set[$class::getName()] = $class;
			}
		}

		return $set;
	}

	public static function buildMap()
	{
		$set = self::getConfig();
		file_put_contents(
			Config::get('__hail.map.di'),
			'<?php return ' . var_export($set, true) . ';'
		);
	}
}