<?php
namespace Hail\Factory;

use Hail\Database\Database as DB;
use Hail\Facades\{
	Config, Serialize
};

class Database extends Factory
{
	/**
	 * @param array $config
	 *
	 * @return DB
	 */
	public static function pdo(array $config = []): DB
	{
		$config += Config::get('database');
		$hash = sha1(Serialize::encode($config));

		if (isset(static::$pool[$hash])) {
			return static::$pool[$hash];
		}

		return static::$pool[$hash] = new DB($config);
	}
}