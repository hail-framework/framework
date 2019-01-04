<?php

namespace Hail\Facade;

use Hail\Session\CsrfToken;
use Hail\Session\Segment;

/**
 * Class Session
 *
 * @package Hail\Facade
 * @see \Hail\Session\Session
 *
 * @method static void setHandler(array $config)
 * @method static Segment getSegment($name)
 * @method static bool isStarted()
 * @method static bool start(array $options = null)
 * @method static void clear()
 * @method static void commit()
 * @method static CsrfToken getCsrfToken()
 * @method static bool isResumable()
 * @method static bool resume()
 * @method static int setCacheExpire(int $expire)
 * @method static int getCacheExpire()
 * @method static string setCacheLimiter(string $limiter)
 * @method static string getCacheLimiter()
 * @method static array getCookieParams()
 * @method static string getId()
 * @method static bool regenerateId()
 * @method static string setName(string $name)
 * @method static string getName()
 * @method static string setSavePath(string $path)
 * @method static string getSavePath()
 * @method static bool destroy()
 * @method static mixed get(string $key)
 * @method static void set(string $key, $value)
 * @method static void delete(string $key)
 */
class Session extends Facade
{
}