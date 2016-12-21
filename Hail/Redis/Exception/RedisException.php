<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2016/12/20 0020
 * Time: 14:10
 */

namespace Hail\Redis\Exception;


/**
 * Credis-specific errors, wraps native Redis errors
 */
class RedisException extends \Exception
{

	const CODE_TIMED_OUT = 1;
	const CODE_DISCONNECTED = 2;

	public function __construct($message, $code = 0, $exception = null)
	{
		if ($exception && get_class($exception) === 'RedisException' && $message === 'read error on connection') {
			$code = self::CODE_DISCONNECTED;
		}
		parent::__construct($message, $code, $exception);
	}
}