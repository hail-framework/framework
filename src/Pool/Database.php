<?php


namespace Hail\Pool;

/**
 * Class ConnectPool
 *
 * @package Hail\Database
 *
 * @method bool|\PDOStatement query(string $sql)
 * @method bool|int exec(string $sql)
 * @method string quote(string $string)
 * @method array|null headers(string $table)
 * @method array|null select(string | array $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method \Generator selectRow(string | array $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method \PDOStatement|null insert(string | array $table, array $datas = [], string | array $INSERT = 'INSERT')
 * @method string|int id()
 * @method \PDOStatement|null update(string | array $table, array $data = [], array | null $where = null)
 * @method \PDOStatement|null delete(string | array $table, array | null $where = null)
 * @method \PDOStatement|null replace(string $table, array $columns, array $where = null)
 * @method mixed get(string | array $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method bool has(array $struct)
 * @method bool|int count(array $struct)
 * @method bool|int|string max(array $struct)
 * @method bool|int min(array $struct)
 * @method bool|int avg(array $struct)
 * @method bool|int sum(array $struct)
 * @method bool|int truncate(string $table)
 * @method bool action(callback | callable $action)
 * @method string error()
 * @method array info()
 */
class Database
{
    use PoolTrait;
}
