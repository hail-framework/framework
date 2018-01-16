<?php

namespace Hail\Database\Cache;

use Hail\Database\Database;
use Hail\Util\Serialize;

trait CacheTrait
{
    /**
     * @var int
     */
    protected $lifetime = 0;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var Database
     */
    protected $db;

    /**
     * @param int $lifetime
     *
     * @return $this
     */
    public function expiresAfter($lifetime = 0)
    {
        $this->lifetime = $lifetime;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function name(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string     $name
     * @param array|null $arguments
     *
     * @return string
     */
    protected function key(string $name, array $arguments = null): string
    {
        if ($this->name !== null) {
            return $this->name;
        }

        if ($arguments === null) {
            return $this->name = $name;
        }

        return $this->name = \sha1(Serialize::encode([$name, $arguments]));
    }

    /**
     * @return $this
     */
    public function reset()
    {
        $this->lifetime = 0;
        $this->name = '';

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $arguments
     *
     * @return bool
     */
    public function delete(string $name, $arguments = null)
    {
        $this->name || $this->key($name, $arguments);
        $result = $this->doDelete();

        $this->reset();

        return $result;
    }

    public function select($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
    {
        return $this->call(__FUNCTION__, $struct, $fetch, $fetchArgs);
    }

    public function get($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
    {
        return $this->call(__FUNCTION__, $struct, $fetch, $fetchArgs);
    }

    protected function call($name, $struct, $fetch, $fetchArgs)
    {
        $this->name || $this->key($name, [$struct, $fetch, $fetchArgs]);

        $cache = $this->doGet();
        if (($result = $this->getResult($cache)) === null) {
            $result = $this->db->$name($struct, $fetch, $fetchArgs);

            $this->doSave($result, $cache);
        }

        $this->reset();

        return $result;
    }

    /**
     * @param      $struct
     * @param int  $fetch
     * @param null $fetchArgs
     *
     * @return \Generator
     */
    public function selectRow($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): \Generator
    {
        $lifetime = $this->lifetime;
        $rowLifetime = $lifetime ? $lifetime + 5 : 0;

        $key = $this->key('selectRow', [$struct, $fetch, $fetchArgs]);

        $countCache = $this->name($key . '_count')->doGet();
        if (($count = $this->getResult($countCache)) === null) {
            $rows = $this->db->selectRow($struct, $fetch, $fetchArgs);
            if (!$rows->valid()) {
                $this->reset();

                return;
            }

            $index = 0;
            foreach ($rows as $row) {
                yield $row;

                $this->name($key . '_' . (string) $index++)
                    ->expiresAfter($rowLifetime)
                    ->doSave($row);
            }

            $this->name($key . '_count')
                ->expiresAfter($lifetime)
                ->doSave((string) $index);
        } else {
            for ($i = 0; $i < $count; ++$i) {
                yield $this->getResult(
                    $this->name($key . '_' . (string) $i)
                        ->doGet()
                );
            }
        }

        $this->reset();
    }

    protected function getResult($cache)
    {
        return $cache;
    }

    abstract protected function doGet();

    abstract protected function doSave($result, $cache = null);

    abstract protected function doDelete();
}