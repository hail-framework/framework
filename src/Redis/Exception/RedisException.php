<?php

namespace Hail\Redis\Exception;

/**
 * Credis-specific errors, wraps native Redis errors
 */
class RedisException extends \Exception
{

    public const CODE_TIMED_OUT = 1;
    public const CODE_DISCONNECTED = 2;

    public function __construct($message, $code = 0, $exception = null)
    {
        if (
            $exception &&
            $message === 'read error on connection' &&
            $exception instanceof \RedisException
        ) {
            $code = self::CODE_DISCONNECTED;
        }

        parent::__construct($message, $code, $exception);
    }
}