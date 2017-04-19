<?php

namespace Hail\SimpleCache\Exception;

use Psr\SimpleCache\CacheException as CacheExceptionInterface;

abstract class CacheException extends \RuntimeException implements CacheExceptionInterface
{
}
