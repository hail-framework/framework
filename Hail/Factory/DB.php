<?php
namespace Hail\Factory;

use Hail\DB\Medoo;
use Hail\Facades\{
	Config, Serialize
};

class DB extends AbstractFactory
{
	/**
	 * @param array $config
	 *
	 * @return Medoo
	 */
	public static function pdo(array $config = [])
	{
		$config += Config::get('database');
		$hash = sha1(Serialize::encode($config));

		if (isset(static::$pool[$hash])) {
			return static::$pool[$hash];
		}

		return static::$pool[$hash] = new Medoo($config);
	}
}