<?php
namespace Hail\Facade;

use Hail\Http\{
	Url, Input, FileUpload, UrlScript
};

/**
 * Class Request
 *
 * @package Hail\Facade
 *
 * @method static Url|UrlScript getUrl()
 * @method static Url|UrlScript cloneUrl()
 * @method static string getPathInfo()
 * @method static mixed input(string $key = null, $default = null)
 * @method static Input getInput()
 * @method static mixed getQuery(string $key = null)
 * @method static mixed getPost(string $key = null)
 * @method static mixed getJson(string $key = null)
 * @method static FileUpload|NULL getFile(string $key = null)
 * @method static mixed getCookie(string $key = null)
 * @method static string getMethod()
 * @method static bool isMethod(string $method)
 * @method static mixed getHeader(string $header, $default = null)
 * @method static array getHeaders()
 * @method static Url|NULL getReferer()
 * @method static bool isSecured()
 * @method static bool isAjax()
 * @method static bool isPjax()
 * @method static bool isJson()
 * @method static bool expectsJson()
 * @method static bool wantsJson()
 * @method static string|NULL getRemoteAddress()
 * @method static string|NULL getRemoteHost()
 * @method static string|NULL getRawBody()
 * @method static string|NULL detectLanguage(array $langs)
 */
class Request extends Facade
{
}