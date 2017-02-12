<?php
namespace Hail\Facades;

use Hail\Factory\Database;

/**
 * Class Database
 *
 * @package Hail\Facades
 *
 * @method static bool|\PDOStatement query(string $sql)
 * @method static bool|int exec(string $sql)
 * @method static void release()
 * @method static string quote(string $string)
 * @method static array headers(string $table)
 * @method static array|bool select(string|array $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method static mixed insert(string $table, array $datas = [], string $INSERT = 'INSERT')
 * @method static string|array lastInsertId()
 * @method static mixed multiInsert(string $table, array $datas = [], string $INSERT = 'INSERT')
 * @method static bool|int update(string $table, array $data = [], array|null $where = null)
 * @method static bool|int delete(string $table, array|null $where = null)
 * @method static bool|int replace(string $table, array $columns, string|array|null $search = null, mixed $replace =null, array $where = null)
 * @method static mixed get(string|array $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method static bool has(array $struct)
 * @method static bool|int count(array $struct)
 * @method static bool|int|string max(array $struct)
 * @method static bool|int min(array $struct)
 * @method static bool|int avg(array $struct)
 * @method static bool|int sum(array $struct)
 * @method static bool|int truncate(string $table)
 * @method static bool action(callback|callable $action)
 * @method static string error()
 * @method static array info()
 */
class DB extends Facade
{
	protected static function instance()
	{
		return Database::pdo();
	}
}