<?php
namespace Hail\Factory;

use Hail\Database\Database;
use Hail\Facades\{
	Config, Serialize
};

class DB extends AbstractFactory
{
	/**
	 * @param array $config
	 *
	 * @return Database
	 */
	public static function pdo(array $config = [])
	{
		$config += Config::get('database');
		$hash = sha1(Serialize::encode($config));

		if (isset(static::$pool[$hash])) {
			return static::$pool[$hash];
		}

		return static::$pool[$hash] = new Database($config);
	}
}