<?php
namespace Hail\Facades;

use Hail\Http\{
	Url, Input, FileUpload, Helpers, UrlScript
};

/**
 * Class Request
 *
 * @package Hail\Facades
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
	protected static function instance()
	{
		$url = new UrlScript();

		$url->setScheme(!empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off') ? 'https' : 'http');
		$url->setUser($_SERVER['PHP_AUTH_USER'] ?? '');
		$url->setPassword($_SERVER['PHP_AUTH_PW'] ?? '');

		// host & port
		if ((isset($_SERVER[$tmp = 'HTTP_HOST']) || isset($_SERVER[$tmp = 'SERVER_NAME']))
			&& preg_match('#^([a-z0-9_.-]+|\[[a-f0-9:]+\])(:\d+)?\z#i', $_SERVER[$tmp], $pair)
		) {
			$url->setHost(strtolower($pair[1]));
			if (isset($pair[2])) {
				$url->setPort((int) substr($pair[2], 1));
			} elseif (isset($_SERVER['SERVER_PORT'])) {
				$url->setPort((int) $_SERVER['SERVER_PORT']);
			}
		}

		// path & query
		$requestUrl = $_SERVER['REQUEST_URI'] ?? '/';
		$path = preg_replace('#^\w++://[^/]++#', '', $requestUrl);
		if (strpos($path, '?') !== false) {
			$path = strstr($path, '?', true);
		}
		$path = Url::unescape($path, '%/?#');
		if (strpos($path, '//') !== false) {
			$path = preg_replace('#/{2,}#', '/', $path);
		}
		$path = htmlspecialchars_decode(
			htmlspecialchars($path, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES
		);
		$url->setPath($path);

		// detect script path
		$lpath = strtolower($path);
		$script = isset($_SERVER['SCRIPT_NAME']) ? strtolower($_SERVER['SCRIPT_NAME']) : '';
		if ($lpath !== $script) {
			$max = min(strlen($lpath), strlen($script));
			for ($i = 0; $i < $max && $lpath[$i] === $script[$i]; $i++) {
				;
			}
			$path = $i ? substr($path, 0, strrpos($path, '/', $i - strlen($path) - 1) + 1) : '/';
		}
		$url->setScriptPath($path);

		$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
		if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
			&& preg_match('#^[A-Z]+\z#', $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
		) {
			$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		$remoteAddr = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
		$remoteHost = !empty($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : null;

		$proxies = Config::get('env.proxies');

		// use real client address and host if trusted proxy is used
		$usingTrustedProxy = $remoteAddr &&
			array_filter($proxies,
				function ($proxy) use ($remoteAddr) {
					return Helpers::ipMatch($remoteAddr, $proxy);
				}
			);
		if ($usingTrustedProxy) {
			if (!empty($_SERVER['HTTP_FORWARDED'])) {
				$forwardParams = preg_split('/[,;]/', $_SERVER['HTTP_FORWARDED']);
				foreach ($forwardParams as $forwardParam) {
					list($key, $value) = explode('=', $forwardParam, 2) + [1 => null];
					$proxyParams[strtolower(trim($key))][] = trim($value, " \t\"");
				}

				if (isset($proxyParams['for'])) {
					$address = $proxyParams['for'][0];
					if (strpos($address, '[') === false) { //IPv4
						$remoteAddr = explode(':', $address)[0];
					} else { //IPv6
						$remoteAddr = substr($address, 1, strpos($address, ']') - 1);
					}
				}

				if (isset($proxyParams['host']) && count($proxyParams['host']) === 1) {
					$host = $proxyParams['host'][0];
					$startingDelimiterPosition = strpos($host, '[');
					if ($startingDelimiterPosition === false) { //IPv4
						$remoteHostArr = explode(':', $host);
						$remoteHost = $remoteHostArr[0];
						if (isset($remoteHostArr[1])) {
							$url->setPort((int) $remoteHostArr[1]);
						}
					} else { //IPv6
						$endingDelimiterPosition = strpos($host, ']');
						$remoteHost = substr($host, strpos($host, '[') + 1, $endingDelimiterPosition - 1);
						$remoteHostArr = explode(':', substr($host, $endingDelimiterPosition));
						if (isset($remoteHostArr[1])) {
							$url->setPort((int) $remoteHostArr[1]);
						}
					}
				}

				$scheme = (isset($proxyParams['proto']) && count($proxyParams['proto']) === 1) ? $proxyParams['proto'][0] : 'http';
				$url->setScheme(strcasecmp($scheme, 'https') === 0 ? 'https' : 'http');
			} else {
				if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
					$url->setScheme(strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0 ? 'https' : 'http');
				}

				if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
					$url->setPort((int) $_SERVER['HTTP_X_FORWARDED_PORT']);
				}

				if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$xForwardedForWithoutProxies = array_filter(
						explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']),
						function ($ip) use ($proxies) {
							return !array_filter($proxies, function ($proxy) use ($ip) {
								return Helpers::ipMatch(trim($ip), $proxy);
							});
						}
					);
					$remoteAddr = trim(end($xForwardedForWithoutProxies));
					$xForwardedForRealIpKey = key($xForwardedForWithoutProxies);
				}

				if (isset($xForwardedForRealIpKey) && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
					$xForwardedHost = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
					if (isset($xForwardedHost[$xForwardedForRealIpKey])) {
						$remoteHost = trim($xForwardedHost[$xForwardedForRealIpKey]);
					}
				}
			}
		}

		return new \Hail\Http\Request($url, $method, $remoteAddr, $remoteHost);
	}
}