<?php

namespace Hail\Database\Cache;

use Hail\Database\Database;
use Hail\Util\Serialize;

trait CacheTrait
{
    protected $lifetime = 0;
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

    protected function call($name, $arguments)
    {
        switch ($name) {
            case 'get':
                return $this->db->get(...$arguments);
            case 'select':
                return $this->db->select(...$arguments);
            default:
                throw new \InvalidArgumentException('Cached database class only support select/get method');
        }
    }

    /**
     * @param            $name
     * @param array|null $arguments
     *
     * @return string
     */
    protected function key($name, $arguments = null)
    {
        if ($this->name) {
            return $this->name;
        }

        if ($arguments === null) {
            return $name;
        }

        if (\is_string($arguments[0])) {
            return $arguments[0];
        }

        return \sha1(Serialize::encode([$name, $arguments]));
    }

    /**
     * @return $this
     */
    public function reset()
    {
        if ($this->lifetime !== 0) {
            $this->lifetime = 0;
        }

        if ($this->name !== '') {
            $this->name = '';
        }

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
        $key = $this->key($name, $arguments);
        $this->reset();

        return $this->doDelete($key);
    }

    abstract protected function doDelete($key);
    abstract public function __call($name, $arguments);
    abstract public function selectRow($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): \Generator;
}