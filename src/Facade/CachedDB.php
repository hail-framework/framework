<?php

namespace Hail\Facade;

use Hail\Database\Cache\CachedDBInterface;

/**
 * Class CachedDB
 *
 * @package Hail\Facade
 * @see     \Hail\Database\Cache\CachedDBInterface
 *
 * @method static mixed select(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 * @method static \Generator selectRow(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 * @method static mixed get(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 * @method static CachedDBInterface expiresAfter(int $lifetime = 0)
 * @method static CachedDBInterface name(string $name)
 * @method static CachedDBInterface reset()
 * @method static bool delete(string $name, mixed $arguments = null)
 */
class CachedDB extends Facade
{
    protected static $name = 'cdb';
}