<?php

namespace Hail\Database\Cache;


interface CachedDBInterface
{
    /**
     * @param int $lifetime
     *
     * @return self
     */
    public function expiresAfter($lifetime = 0);

    /**
     * @param string $name
     *
     * @return self
     */
    public function name(string $name);


    /**
     * @return self
     */
    public function reset();

    /**
     * @param string     $name
     * @param array|null $arguments
     *
     * @return bool
     */
    public function delete(string $name, $arguments = null);


    /**
     * @param array|string $struct
     * @param int          $fetch
     * @param mixed        $fetchArgs
     *
     * @return \Generator
     */
    public function selectRow($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): \Generator;

    /**
     * @param array|string $struct
     * @param int          $fetch
     * @param mixed        $fetchArgs
     *
     * @return array|null
     */
    public function select($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null);

    /**
     * @param array|string $struct
     * @param int          $fetch
     * @param mixed        $fetchArgs
     *
     * @return array|string|null
     */
    public function get($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null);
}