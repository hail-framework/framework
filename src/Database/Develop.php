<?php

namespace Hail\Database;

use Hail\Database\Event\Event;

class Develop extends Database
{
    /** @var Event */
    protected $event;

    protected function connect()
    {
        [$dsn, $username, $options, $commands] = $this->dsn;
        $password = $this->getPassword();

        $this->event('start', Event::CONNECT);

        $this->pdo = new \PDO(
            $dsn,
            $username,
            $password,
            $options
        );

        $this->event('done');

        foreach ($commands as $value) {
            $this->event('sql', $value);
            $return = $this->pdo->exec($value);
            $this->event('query');
            $this->done($return);
        }
    }

    public function exec(string $query, array $map = [], array $fetchArgs = null): ?\PDOStatement
    {
        $this->event('sql', $this->sql->generate($query, $map));
        $return = parent::exec($query, $map, $fetchArgs);
        $this->event('query');

        return $return;
    }

    /**
     * @param mixed $result
     *
     * @return mixed
     */
    protected function done($result = null)
    {
        if ($result === false &&
            ($error = $this->error()) &&
            isset($error[0])
        ) {
            $this->event('error', $error);
        }

        $this->event('done', $result);
        $this->event = null;

        return $result;
    }

    public function headers(string $table): ?array
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::headers($table)
        );
    }

    public function select($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): ?array
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::select($struct, $fetch, $fetchArgs)
        );
    }

    public function selectRow($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): \Generator
    {
        $this->event('start', Event::SELECT);

        $rows = parent::selectRow($struct, $fetch, $fetchArgs);
        if (!$rows->valid()) {
            return;
        }

        $result = [];
        foreach ($rows as $row) {
            yield $row;
            $result[] = $row;
        }

        $this->done($result);
    }

    public function insert($table, array $data = [], string $INSERT = 'INSERT'): ?\PDOStatement
    {
        $this->event('start', Event::INSERT);

        return $this->done(
            parent::insert($table, $data, $INSERT)
        );
    }

    public function update($table, $data = [], $where = null): ?\PDOStatement
    {
        $this->event('start', Event::UPDATE);

        return $this->done(
            parent::update($table, $data, $where)
        );
    }

    public function delete($table, $where = null): ?\PDOStatement
    {
        $this->event('start', Event::DELETE);

        return $this->done(
            parent::delete($table, $where)
        );
    }

    public function replace($table, array $columns = null, array $where = null): ?\PDOStatement
    {
        $this->event('start', Event::UPDATE);

        return $this->done(
            parent::replace($table, $columns, $where)
        );
    }

    public function get($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::get($struct, $fetch, $fetchArgs)
        );
    }

    public function has(array $struct): bool
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::has($struct)
        );
    }

    public function count(array $struct)
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::count($struct)
        );
    }

    public function max(array $struct)
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::max($struct)
        );
    }

    public function min(array $struct)
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::min($struct)
        );
    }

    public function avg(array $struct)
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::avg($struct)
        );
    }

    public function sum(array $struct)
    {
        $this->event('start', Event::SELECT);

        return $this->done(
            parent::sum($struct)
        );
    }

    public function truncate(string $table): ?\PDOStatement
    {
        $this->event('start', Event::TRUNCATE);

        return $this->done(
            parent::truncate($table)
        );
    }

    public function action(callable $actions)
    {
        $pdo = $this->pdo ?? $this->getPdo();
        $event = new Event($this, Event::TRANSACTION);
        $pdo->beginTransaction();

        try {
            $result = $actions($this);

            $event->query();
            $this->event = $event;

            if ($result === false) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->done($e);

            throw $e;
        }

        return $this->done($result);
    }

    public function id()
    {
        $sql = self::ID_SQL[$this->type] ?? null;

        if ($sql !== null) {
            $this->event('start', Event::SELECT);
            $this->event('sql', $sql);
        }

        $return = parent::id();

        if ($sql !== null) {
            $this->event('query');
            return $this->done($return);
        }

        return $return;
    }

    protected function event($type, $arg = null)
    {
        switch ($type) {
            case 'start':
                if ($this->pdo === null && $arg !== Event::CONNECT) {
                    $this->connect();
                }

                $this->event = new Event($this, $arg);
                break;

            case 'sql':
                if ($this->event === null) {
                    $this->event('start', Event::QUERY);
                    $this->event->sql($arg, false);
                } else {
                    $this->event->sql($arg);
                }
                break;

            case 'query':
                if ($this->event !== null) {
                    $this->event->query();
                }
                break;

            case 'done':
                if ($this->event !== null) {
                    if ($arg instanceof \PDOStatement) {
                        $arg = $arg->rowCount();
                    }

                    $this->event->done($arg);
                    $this->event = null;
                }
                break;

            case 'error':
                if ($this->event !== null) {
                    $this->event->error($arg);
                }
                break;
        }
    }
}
