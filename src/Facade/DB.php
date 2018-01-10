<?php

namespace Hail\Facade;

/**
 * Class DB
 *
 * @package Hail\Facade
 *
 * @method static bool|\PDOStatement query(string $sql)
 * @method static bool|int exec(string $sql)
 * @method static string quote(string $string)
 * @method static array|null headers(string $table)
 * @method static array|null select(string | array $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method static \Generator selectRow(string | array $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method static \PDOStatement|null insert(string | array $table, array $datas = [], string | array $INSERT = 'INSERT')
 * @method static string|int id()
 * @method static \PDOStatement|null update(string | array $table, array $data = [], array | null $where = null)
 * @method static \PDOStatement|null delete(string | array $table, array | null $where = null)
 * @method static \PDOStatement|null replace(string $table, array $columns, array $where = null)
 * @method static mixed get(string | array $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method static bool has(array $struct)
 * @method static bool|int count(array $struct)
 * @method static bool|int|string max(array $struct)
 * @method static bool|int min(array $struct)
 * @method static bool|int avg(array $struct)
 * @method static bool|int sum(array $struct)
 * @method static bool|int truncate(string $table)
 * @method static bool action(callback | callable $action)
 * @method static string error()
 * @method static array info()
 */
class DB extends Facade
{
}