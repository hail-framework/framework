<?php
namespace Hail\Facade;

use Hail\Http;

/**
 * Class Response
 *
 * @package Hail\Facade
 * @method static disableWarnOnBuffer()
 * @method static Http\Response setCode(int $code)
 * @method static int getCode()
 * @method static Http\Response setHeader(string $name, string $value)
 * @method static Http\Response addHeader(string $name, string $value)
 * @method static Http\Response setContentType(string $type, string $charset = null)
 * @method static redirect(string $url, int $code = Http\Response::S302_FOUND)
 * @method static Http\Response setExpiration(string|int|\DateTime $time)
 * @method static Http\Response setOrigin(string $domain)
 * @method static bool isSent()
 * @method static mixed getHeader(string $header, $default = null)
 * @method static array getHeaders()
 * @method static Http\Response setCookie($name, $value, $time, $path = null, $domain = null, $secure = null, $httpOnly = null)
 * @method static deleteCookie($name, $path = null, $domain = null, $secure = null)
 */
class Response extends Facade
{
	protected static function instance()
	{
		return new Http\Response();
	}
}