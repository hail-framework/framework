<?php

namespace Hail\Database;

use Hail\Pool\WorkerInterface;
use Hail\Pool\WorkerTrait;

class ConnectPool extends Database implements WorkerInterface
{
    use WorkerTrait;

    /**
     * @param mixed $result
     *
     * @return mixed
     */
    protected function done($result = null)
    {
        $this->release();

        return $result;
    }

    public function headers(string $table): ?array
    {
        return $this->done(
            parent::headers($table)
        );
    }

    public function select($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): ?array
    {
        return $this->done(
            parent::select($struct, $fetch, $fetchArgs)
        );
    }

    public function selectRow($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): \Generator
    {
        $rows = parent::selectRow($struct, $fetch, $fetchArgs);
        if (!$rows->valid()) {
            return;
        }

        foreach ($rows as $row) {
            yield $row;
        }

        $this->done();
    }

    public function insert($table, array $data = [], string $INSERT = 'INSERT'): ?\PDOStatement
    {
        return $this->done(
            parent::insert($table, $data, $INSERT)
        );
    }

    public function update($table, $data = [], $where = null): ?\PDOStatement
    {
        return $this->done(
            parent::update($table, $data, $where)
        );
    }

    public function delete($table, $where = null): ?\PDOStatement
    {
        return $this->done(
            parent::delete($table, $where)
        );
    }

    public function replace($table, array $columns = null, array $where = null): ?\PDOStatement
    {
        return $this->done(
            parent::replace($table, $columns, $where)
        );
    }

    /**
     * @param array          $struct
     * @param int            $fetch
     * @param int|array|null $fetchArgs
     *
     * @return array|bool
     */
    public function get($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
    {
        return $this->done(
            parent::get($struct, $fetch, $fetchArgs)
        );
    }

    public function has(array $struct): bool
    {
        return $this->done(
            parent::has($struct)
        );
    }

    /**
     * @param array $struct
     *
     * @return bool|int
     */
    public function count(array $struct)
    {
        return $this->done(
            parent::count($struct)
        );
    }

    /**
     * @param array $struct
     *
     * @return bool|int|string
     */
    public function max(array $struct)
    {
        return $this->done(
            parent::max($struct)
        );
    }

    /**
     * @param array $struct
     *
     * @return bool|int|string
     */
    public function min(array $struct)
    {
        return $this->done(
            parent::min($struct)
        );
    }

    /**
     * @param array $struct
     *
     * @return bool|int
     */
    public function avg(array $struct)
    {
        return $this->done(
            parent::avg($struct)
        );
    }

    /**
     * @param array $struct
     *
     * @return bool|int
     */
    public function sum(array $struct)
    {
        return $this->done(
            parent::sum($struct)
        );
    }

    public function truncate(string $table): ?\PDOStatement
    {
        return $this->done(
            parent::truncate($table)
        );
    }

    /**
     * @param callable $actions
     *
     * @return mixed
     * @throws \Throwable
     */
    public function action(callable $actions)
    {
        return $this->done(
            parent::action($actions)
        );
    }

    public function id()
    {
        $return = parent::id();

        if (isset(self::ID_SQL[$this->type])) {
            return $this->done($return);
        }

        return $return;
    }
}
