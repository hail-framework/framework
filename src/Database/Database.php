<?php
/*!
 * Medoo database framework
 * http://medoo.in
 * Version 1.6
 *
 * Copyright 2017, Angel Lai
 * Released under the MIT license
 */

namespace Hail\Database;

use Hail\Database\Sql\Builder;
use Hail\Util\SafeStorageTrait;

/**
 * Part of the code from Medoo
 *
 * @package Hail\Database
 * @author  Feng Hao <flyinghail@msn.com>
 */
class Database
{
    use SafeStorageTrait;

    // General
    protected $type;
    protected $database;

    /**
     * @var \PDO $pdo
     */
    protected $pdo;

    /**
     * @var \PDOStatement|null
     */
    protected $statement;

    /**
     * @var Builder
     */
    protected $sql;

    protected $debug = false;

    /**
     * @var array
     */
    protected $dsn;

    protected const ID_SQL = [
        'mssql' => 'SELECT SCOPE_IDENTITY()',
        'pgsql' => 'SELECT LASTVAL()',
    ];

    public function __construct(array $options)
    {
        $this->init($options);
    }

    public function __destruct()
    {
        $this->pdo = $this->statement = $this->sql = null;
    }

    /**
     * @param array $options
     *
     * @return static
     */
    public function init(array $options = [])
    {
        if (isset($options['type'])) {
            $this->type = \strtolower($options['type']);
        }

        $pdoOptions = [
            \PDO::ATTR_CASE => \PDO::CASE_NATURAL,
        ];

        if (isset($options['option'])) {
            $pdoOptions = $options['option'] + $pdoOptions;
        }

        $commands = [];
        if (isset($options['command']) && \is_array($options['command'])) {
            $commands = $options['command'];
        }

        $port = null;
        if (isset($options['port'])) {
            $port = ((int) $options['port']) ?: null;
        }

        if (isset($options['dsn'])) {
            if (\is_array($options['dsn']) && isset($options['dsn']['driver'])) {
                $attr = $options['dsn'];

                $this->type = \strtolower($attr['driver']);
            } else {
                throw new \InvalidArgumentException('Invalid DSN option supplied');
            }
        } else {
            $attr = [
                'driver' => $this->type,
                'host' => $options['server'] ?? null,
                'dbname' => $options['database'] ?? null,
                'port' => $port,
            ];

            switch ($this->type) {
                case 'mariadb':
                    $attr['driver'] = 'mysql';
                case 'mysql':
                    if (isset($options['socket'])) {
                        $attr['unix_socket'] = $options['socket'];
                        unset($attr['host'], $attr['port']);
                    }

                    if (isset($options['charset'])) {
                        if (isset($options['collation'])) {
                            $commands[] = "SET NAMES '{$options[ 'charset' ]}'" . (
                                isset($options['collation']) ?
                                    " COLLATE '{$options[ 'collation' ]}'" : ''
                                );
                        } else {
                            $attr['charset'] = $options['charset'];
                        }
                    }

                    break;

                case 'pgsql':
                    if (isset($options['charset'])) {
                        $commands[] = "SET NAMES '{$options['charset']}'";
                    }
                    break;

                case 'sybase':
                    $attr['driver'] = 'dblib';
                    if (isset($options['charset'])) {
                        $attr['charset'] = $options['charset'];
                    }
                    break;

                case 'oracle':
                    $attr['driver'] = $attr['oci'];
                    if ($attr['host']) {
                        $attr['dbname'] = '//' . $attr['host'] . ':' . ($attr['port'] ?? '1521') . '/' . $attr['dbname'];

                    }
                    unset($attr['host'], $attr['port']);

                    if (isset($options['charset'])) {
                        $attr['charset'] = $options['charset'];
                    }
                    break;

                case 'mssql':
                    $attr['driver'] = \stripos(PHP_OS, 'WIN') === 0 ? 'sqlsrv' : 'dblib';

                    // Keep MSSQL QUOTED_IDENTIFIER is ON for standard quoting
                    $commands[] = 'SET QUOTED_IDENTIFIER ON';
                    // Make ANSI_NULLS is ON for NULL value
                    $commands[] = 'SET ANSI_NULLS ON';


                    if ($attr['driver'] === 'dblib') {
                        if (isset($options['appname'])) {
                            $attr['appname'] = $options['appname'];
                        }

                        if (isset($options['charset'])) {
                            $attr['charset'] = $options['charset'];
                        }
                    } else {
                        if (isset($options['appname'])) {
                            $attr['APP'] = $options['appname'];
                        }

                        $config = [
                            'ApplicationIntent',
                            'AttachDBFileName',
                            'Authentication',
                            'ColumnEncryption',
                            'ConnectionPooling',
                            'Encrypt',
                            'Failover_Partner',
                            'KeyStoreAuthentication',
                            'KeyStorePrincipalId',
                            'KeyStoreSecret',
                            'LoginTimeout',
                            'MultipleActiveResultSets',
                            'MultiSubnetFailover',
                            'Scrollable',
                            'TraceFile',
                            'TraceOn',
                            'TransactionIsolation',
                            'TransparentNetworkIPResolution',
                            'TrustServerCertificate',
                            'WSID',
                        ];

                        foreach ($config as $value) {
                            $keyname = \strtolower(
                                \preg_replace(
                                    [
                                        '/([a-z\d])([A-Z])/',
                                        '/([^_])([A-Z][a-z])/',
                                    ], '$1_$2', $value
                                )
                            );

                            if (isset($options[$keyname])) {
                                $attr[$value] = $options[$keyname];
                            }
                        }

                        if (isset($options['charset'])) {
                            $commands[] = "SET NAMES '{$options['charset']}'";
                        }
                    }

                    break;

                case 'sqlite':
                    $options['username'] = null;
                    $options['password'] = null;
                    $options['database'] = $options['file'];
                    $attr = [
                        0 => $options['file'],
                        'driver' => $this->type,
                    ];
                    break;
            }
        }

        $this->database = $options['database'] ?? $attr['dbname'];
        $driver = $attr['driver'];
        unset($attr['driver']);

        if (!\in_array($driver, \PDO::getAvailableDrivers(), true)) {
            throw new \InvalidArgumentException("Unsupported PDO driver: {$driver}");
        }

        $stack = [];
        foreach ($attr as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (\is_int($key)) {
                $stack[] = $value;
            } else {
                $stack[] = $key . '=' . $value;
            }
        }
        $dsn = $driver . ':' . \implode(';', $stack);

        $this->setPassword($options['password']);
        $this->dsn = [$dsn, $options['username'], $pdoOptions, $commands];
        $this->sql = new Builder($this, $options['prefix'] ?? '');

        return $this;
    }

    public function debug()
    {
        $this->debug = true;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    protected function connect()
    {
        [$dsn, $username, $options, $commands] = $this->dsn;
        $password = $this->getPassword();

        $this->pdo = new \PDO(
            $dsn,
            $username,
            $password,
            $options
        );

        foreach ($commands as $value) {
            $this->pdo->exec($value);
        }
    }

    public function use(string $db): bool
    {
        if ($this->database === $db) {
            return true;
        }

        $pdo = $this->pdo ?? $this->getPdo();
        if ($pdo->exec('USE `' . $db . '`') !== false) {
            $this->database = $db;
            return true;
        }

        return false;
    }

    public function sql(): Builder
    {
        return $this->sql;
    }

    public function raw(string $string, array $map = []): Sql\Raw
    {
        return $this->sql->raw($string, $map);
    }

    /**
     * @param string $query
     * @param array $map
     *
     * @return \PDOStatement|null
     */
    public function query(string $query, array $map = []): ?\PDOStatement
    {
        $raw = $this->sql->raw($query, $map);
        $sql = $this->sql->buildRaw($raw, $map);

        return $this->exec($sql, $map);
    }

    /**
     * @param string $query
     * @param array $map
     * @param array $fetchArgs
     *
     * @return \PDOStatement|null
     */
    public function exec(string $query, array $map = [], array $fetchArgs = null): ?\PDOStatement
    {
        $pdo = $this->pdo ?? $this->getPdo();

        if ($this->debug) {
            echo $this->sql->generate($query, $map);

            $this->debug = false;

            return null;
        }

        $retries = 0;

        RETRY_QUERY:
        {
            try {
                $statement = $pdo->prepare($query);

                if ($statement) {
                    $this->statement = $statement;

                    foreach ($map as $key => $value) {
                        $statement->bindValue($key, $value[0], $value[1]);
                    }

                    if (!empty($fetchArgs)) {
                        $statement->setFetchMode(...$fetchArgs);
                    }

                    if ($statement->execute()) {
                        return $statement;
                    }
                }
            } catch (\PDOException $e) {
                if ($retries === 0) {
                    $error = $e->getMessage();
                    if (
                        \strpos($error, '2006 MySQL server has gone away') !== false ||
                        \strpos($error, '2013 Lost connection') !== false
                    ) {
                        $this->pdo = $pdo = null;
                        $pdo = $this->getPdo();

                        ++$retries;
                        goto RETRY_QUERY;
                    }
                }
            }
        }

        return null;
    }


    /**
     * @param $string
     *
     * @return string
     */
    public function quote($string)
    {
        $pdo = $this->pdo ?? $this->getPdo();

        return $pdo->quote($string);
    }

    /**
     * @param string $table
     *
     * @return array|null
     */
    public function headers(string $table): ?array
    {
        [$sql] = $this->sql->select($table);
        $query = $this->exec($sql);

        $headers = null;
        if ($query) {
            $headers = [];
            for ($i = 0, $n = $query->columnCount(); $i < $n; ++$i) {
                $headers[] = $query->getColumnMeta($i);
            }
        }

        return $headers;
    }

    /**
     * @param array|string $struct
     * @param int $fetch
     * @param mixed $fetchArgs
     *
     * @return array|null
     */
    public function select($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): ?array
    {
        [$sql, $map] = $this->sql->select($struct);
        $query = $this->exec($sql, $map);

        $return = null;
        if ($query) {
            if ($fetchArgs !== null && (
                    ($fetch & \PDO::FETCH_CLASS) ||
                    ($fetch & \PDO::FETCH_COLUMN) ||
                    ($fetch & \PDO::FETCH_FUNC)
                )
            ) {
                $fetchArgs = (array) $fetchArgs;
                $return = $query->fetchAll($fetch, ...$fetchArgs);
            } else {
                $return = $query->fetchAll($fetch);
            }
        }

        return $return;
    }

    /**
     * @code
     *
     *      $rows = $db->selectRow($sql);
     *      if (!$rows->valid()) {
     *          //error
     *      }
     *      foreach ($rows as $row) {
     *          // do with $row
     *      }
     *
     * @param array|string $struct
     * @param int $fetch
     * @param mixed $fetchArgs
     *
     * @return \Generator
     */
    public function selectRow($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): \Generator
    {
        [$sql, $map] = $this->sql->select($struct);

        if ($fetchArgs !== null && (
                ($fetch & \PDO::FETCH_CLASS) ||
                ($fetch & \PDO::FETCH_COLUMN) ||
                ($fetch & \PDO::FETCH_INTO)
            )
        ) {
            $fetchArgs = (array) $fetchArgs;
            \array_unshift($fetchArgs, $fetch);
        }

        $query = $this->exec($sql, $map, $fetchArgs);

        if (!$query) {
            return;
        }

        while ($row = $query->fetch($fetch)) {
            yield $row;
        }
    }

    /**
     * @param string|array $table
     * @param array $data
     * @param string $INSERT
     *
     * @return \PDOStatement|null
     */
    public function insert($table, array $data = [], string $INSERT = 'INSERT'): ?\PDOStatement
    {
        [$sql, $map] = $this->sql->insert($table, $data, $INSERT);

        return $this->exec($sql, $map);
    }

    /**
     * @return mixed
     */
    public function id()
    {
        $type = $this->type;

        if ($type === 'oracle') {
            return 0;
        }

        $pdo = $this->pdo ?? $this->getPdo();

        if (isset(self::ID_SQL[$type])) {
            return $pdo->query(self::ID_SQL[$type])->fetchColumn();
        }

        return $pdo->lastInsertId();
    }

    /**
     * @param string|array $table
     * @param array $data
     * @param null $where
     *
     * @return \PDOStatement|null
     */
    public function update($table, $data = [], $where = null): ?\PDOStatement
    {
        [$sql, $map] = $this->sql->update($table, $data, $where);

        return $this->exec($sql, $map);
    }

    /**
     * @param string|array $table
     * @param null $where
     *
     * @return \PDOStatement|null
     */
    public function delete($table, $where = null): ?\PDOStatement
    {
        [$sql, $map] = $this->sql->delete($table, $where);

        return $this->exec($sql, $map);
    }

    /**
     * @param string|array $table
     * @param array|null $columns
     * @param array|null $where
     *
     * @return \PDOStatement|null
     */
    public function replace($table, array $columns = null, array $where = null): ?\PDOStatement
    {
        $return = $this->sql->replace($table, $columns, $where);
        if ($return === null) {
            return null;
        }

        [$sql, $map] = $return;

        return $this->exec($sql, $map);
    }

    /**
     * @param array|string $struct
     * @param int $fetch
     * @param mixed $fetchArgs
     *
     * @return array|string|null
     */
    public function get($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
    {
        $struct['LIMIT'] = 1;
        [$sql, $map] = $this->sql->select($struct);

        if ($fetchArgs !== null && (
                ($fetch & \PDO::FETCH_CLASS) ||
                ($fetch & \PDO::FETCH_COLUMN) ||
                ($fetch & \PDO::FETCH_INTO)
            )
        ) {
            $fetchArgs = (array) $fetchArgs;
            \array_unshift($fetchArgs, $fetch);
        }

        $query = $this->exec($sql, $map, $fetchArgs);

        $return = null;
        if ($query) {
            $return = $query->fetch($fetch);

            if (\is_array($return) && !empty($return)) {
                $column = $this->sql->getColumns($struct);

                if (\is_string($column)) {
                    if ($column !== '*') {
                        $return = $return[$column];
                    }
                } elseif (\is_array($column) && \count($column) === 1) {
                    $return = $return[\current($column)];
                }
            }
        }

        return $return;
    }

    /**
     * @param array $struct
     *
     * @return bool
     */
    public function has(array $struct): bool
    {
        [$sql, $map] = $this->sql->has($struct);
        $query = $this->exec($sql, $map);

        $return = false;
        if ($query) {
            $return = $query->fetchColumn();
        }

        return $return === '1';
    }

    public function rand($struct)
    {
        return $this->select(
            $this->sql->rand($struct)
        );
    }

    private function aggregate($type, array $struct)
    {
        $struct['FUN'] = \strtoupper($type);
        [$sql, $map] = $this->sql->select($struct);

        $query = $this->exec($sql, $map);

        if ($query) {
            $number = $query->fetchColumn();

            return \is_numeric($number) ? (int) $number : $number;
        }

        return false;
    }

    /**
     * @param array $struct
     *
     * @return bool|int
     */
    public function count(array $struct)
    {
        return $this->aggregate('count', $struct);
    }

    /**
     * @param array $struct
     *
     * @return bool|int|string
     */
    public function max(array $struct)
    {
        return $this->aggregate('max', $struct);
    }

    /**
     * @param array $struct
     *
     * @return bool|int|string
     */
    public function min(array $struct)
    {
        return $this->aggregate('min', $struct);
    }

    /**
     * @param array $struct
     *
     * @return bool|int
     */
    public function avg(array $struct)
    {
        return $this->aggregate('avg', $struct);
    }

    /**
     * @param array $struct
     *
     * @return bool|int
     */
    public function sum(array $struct)
    {
        return $this->aggregate('sum', $struct);
    }

    /**
     * @param string $table
     *
     * @return null|\PDOStatement
     */
    public function truncate(string $table): ?\PDOStatement
    {
        $sql = $this->sql->truncate($table);

        return $this->exec($sql);
    }

    /**
     * @param callable $actions
     *
     * @return mixed
     * @throws \Throwable
     */
    public function action(callable $actions)
    {
        $pdo = $this->pdo ?? $this->getPdo();
        $pdo->beginTransaction();

        try {
            $result = $actions($this);

            if ($result === false) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        return $result;
    }

    public function error()
    {
        return $this->statement ? $this->statement->errorInfo() : $this->pdo->errorInfo();
    }

    public function info()
    {
        $output = [
            'server' => 'SERVER_INFO',
            'driver' => 'DRIVER_NAME',
            'client' => 'CLIENT_VERSION',
            'version' => 'SERVER_VERSION',
            'connection' => 'CONNECTION_STATUS',
        ];

        $pdo = $this->pdo ?? $this->getPdo();
        foreach ($output as $key => $value) {
            $output[$key] = @$pdo->getAttribute(\constant('\PDO::ATTR_' . $value));
        }

        $output['dsn'] = $this->dsn[0];

        return $output;
    }
}
