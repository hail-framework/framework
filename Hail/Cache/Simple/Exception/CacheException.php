<?php

namespace Hail\Cache\Simple\Exception;

use Psr\SimpleCache\CacheException as CacheExceptionInterface;

abstract class CacheException extends \RuntimeException implements CacheExceptionInterface
{
}
