<?php

namespace Hail\Browser;

class Request
{
	private static $cookie = null;
	private static $cookieFile = null;
	private static $curlOpts = [];
	private static $defaultHeaders = [];
	private static $handle = null;
	private static $socketTimeout = null;
	private static $verifyPeer = true;
	private static $verifyHost = true;

	private static $auth = [
		'user' => '',
		'pass' => '',
		'method' => CURLAUTH_BASIC,
	];

	private static $proxy = [
		'port' => false,
		'tunnel' => false,
		'address' => false,
		'type' => CURLPROXY_HTTP,
		'auth' => [
			'user' => '',
			'pass' => '',
			'method' => CURLAUTH_BASIC,
		],
	];

	/**
	 * Verify SSL peer
	 *
	 * @param bool $enabled enable SSL verification, by default is true
	 *
	 * @return bool
	 */
	public static function verifyPeer($enabled)
	{
		return self::$verifyPeer = $enabled;
	}

	/**
	 * Verify SSL host
	 *
	 * @param bool $enabled enable SSL host verification, by default is true
	 *
	 * @return bool
	 */
	public static function verifyHost($enabled)
	{
		return self::$verifyHost = $enabled;
	}

	/**
	 * Set a timeout
	 *
	 * @param integer $seconds timeout value in seconds
	 *
	 * @return integer
	 */
	public static function timeout($seconds)
	{
		return self::$socketTimeout = $seconds;
	}

	/**
	 * Set default headers to send on every request
	 *
	 * @param array $headers headers array
	 *
	 * @return array
	 */
	public static function defaultHeaders($headers)
	{
		return self::$defaultHeaders = array_merge(self::$defaultHeaders, $headers);
	}

	/**
	 * Set a new default header to send on every request
	 *
	 * @param string $name header name
	 * @param string $value header value
	 *
	 * @return string
	 */
	public static function defaultHeader($name, $value)
	{
		return self::$defaultHeaders[$name] = $value;
	}

	/**
	 * Clear all the default headers
	 */
	public static function clearDefaultHeaders()
	{
		return self::$defaultHeaders = [];
	}

	/**
	 * Set curl options to send on every request
	 *
	 * @param array $options options array
	 *
	 * @return array
	 */
	public static function curlOpts($options)
	{
		return self::mergeCurlOptions(self::$curlOpts, $options);
	}

	/**
	 * Set a new default header to send on every request
	 *
	 * @param string $name header name
	 * @param string $value header value
	 *
	 * @return string
	 */
	public static function curlOpt($name, $value)
	{
		return self::$curlOpts[$name] = $value;
	}

	/**
	 * Clear all the default headers
	 */
	public static function clearCurlOpts()
	{
		return self::$curlOpts = [];
	}

	/**
	 * Set a Mashape key to send on every request as a header
	 * Obtain your Mashape key by browsing one of your Mashape applications on https://www.mashape.com
	 *
	 * Note: Mashape provides 2 keys for each application: a 'Testing' and a 'Production' one.
	 *       Be aware of which key you are using and do not share your Production key.
	 *
	 * @param string $key Mashape key
	 *
	 * @return string
	 */
	public static function setMashapeKey($key)
	{
		return self::defaultHeader('X-Mashape-Key', $key);
	}

	/**
	 * Set a cookie string for enabling cookie handling
	 *
	 * @param string $cookie
	 */
	public static function cookie($cookie)
	{
		self::$cookie = $cookie;
	}

	/**
	 * Set a cookie file path for enabling cookie handling
	 *
	 * $cookieFile must be a correct path with write permission
	 *
	 * @param string $cookieFile - path to file for saving cookie
	 */
	public static function cookieFile($cookieFile)
	{
		self::$cookieFile = $cookieFile;
	}

	/**
	 * Set authentication method to use
	 *
	 * @param string $username authentication username
	 * @param string $password authentication password
	 * @param integer $method authentication method
	 */
	public static function auth($username = '', $password = '', $method = CURLAUTH_BASIC)
	{
		self::$auth['user'] = $username;
		self::$auth['pass'] = $password;
		self::$auth['method'] = $method;
	}

	/**
	 * Set proxy to use
	 *
	 * @param string $address proxy address
	 * @param integer $port proxy port
	 * @param integer $type (Available options for this are CURLPROXY_HTTP, CURLPROXY_HTTP_1_0 CURLPROXY_SOCKS4, CURLPROXY_SOCKS5, CURLPROXY_SOCKS4A and CURLPROXY_SOCKS5_HOSTNAME)
	 * @param bool $tunnel enable/disable tunneling
	 */
	public static function proxy($address, $port = 1080, $type = CURLPROXY_HTTP, $tunnel = false)
	{
		self::$proxy['type'] = $type;
		self::$proxy['port'] = $port;
		self::$proxy['tunnel'] = $tunnel;
		self::$proxy['address'] = $address;
	}

	/**
	 * Set proxy authentication method to use
	 *
	 * @param string $username authentication username
	 * @param string $password authentication password
	 * @param integer $method authentication method
	 */
	public static function proxyAuth($username = '', $password = '', $method = CURLAUTH_BASIC)
	{
		self::$proxy['auth']['user'] = $username;
		self::$proxy['auth']['pass'] = $password;
		self::$proxy['auth']['method'] = $method;
	}

	/**
	 * Send a GET request to a URL
	 *
	 * @param string $url URL to send the GET request to
	 * @param array $headers additional headers to send
	 * @param mixed $parameters parameters to send in the querystring
	 *
	 * @return Response
	 */
	public static function get($url, $headers = [], $parameters = null)
	{
		return self::send(Method::GET, $url, $parameters, $headers);
	}

	/**
	 * Send a HEAD request to a URL
	 *
	 * @param string $url URL to send the HEAD request to
	 * @param array $headers additional headers to send
	 * @param mixed $parameters parameters to send in the querystring
	 *
	 * @return Response
	 */
	public static function head($url, $headers = [], $parameters = null)
	{
		return self::send(Method::HEAD, $url, $parameters, $headers);
	}

	/**
	 * Send a OPTIONS request to a URL
	 *
	 * @param string $url URL to send the OPTIONS request to
	 * @param array $headers additional headers to send
	 * @param mixed $parameters parameters to send in the querystring
	 *
	 * @return Response
	 */
	public static function options($url, $headers = [], $parameters = null)
	{
		return self::send(Method::OPTIONS, $url, $parameters, $headers);
	}

	/**
	 * Send a CONNECT request to a URL
	 *
	 * @param string $url URL to send the CONNECT request to
	 * @param array $headers additional headers to send
	 * @param mixed $parameters parameters to send in the querystring
	 *
	 * @return Response
	 */
	public static function connect($url, $headers = [], $parameters = null)
	{
		return self::send(Method::CONNECT, $url, $parameters, $headers);
	}

	/**
	 * Send POST request to a URL
	 *
	 * @param string $url URL to send the POST request to
	 * @param array $headers additional headers to send
	 * @param mixed $body POST body data
	 *
	 * @return Response response
	 */
	public static function post($url, $headers = [], $body = null)
	{
		return self::send(Method::POST, $url, $body, $headers);
	}

	/**
	 * Send DELETE request to a URL
	 *
	 * @param string $url URL to send the DELETE request to
	 * @param array $headers additional headers to send
	 * @param mixed $body DELETE body data
	 *
	 * @return Response
	 */
	public static function delete($url, $headers = [], $body = null)
	{
		return self::send(Method::DELETE, $url, $body, $headers);
	}

	/**
	 * Send PUT request to a URL
	 *
	 * @param string $url URL to send the PUT request to
	 * @param array $headers additional headers to send
	 * @param mixed $body PUT body data
	 *
	 * @return Response
	 */
	public static function put($url, $headers = [], $body = null)
	{
		return self::send(Method::PUT, $url, $body, $headers);
	}

	/**
	 * Send PATCH request to a URL
	 *
	 * @param string $url URL to send the PATCH request to
	 * @param array $headers additional headers to send
	 * @param mixed $body PATCH body data
	 *
	 * @return Response
	 */
	public static function patch($url, $headers = [], $body = null)
	{
		return self::send(Method::PATCH, $url, $body, $headers);
	}

	/**
	 * Send TRACE request to a URL
	 *
	 * @param string $url URL to send the TRACE request to
	 * @param array $headers additional headers to send
	 * @param mixed $body TRACE body data
	 *
	 * @return Response
	 */
	public static function trace($url, $headers = [], $body = null)
	{
		return self::send(Method::TRACE, $url, $body, $headers);
	}

	/**
	 * This function is useful for serializing multidimensional arrays, and avoid getting
	 * the 'Array to string conversion' notice
	 *
	 * @param array|object $data array to flatten.
	 * @param bool|string $parent parent key or false if no parent
	 *
	 * @return array
	 */
	public static function buildHTTPCurlQuery($data, $parent = false)
	{
		$result = [];

		if (is_object($data)) {
			$data = get_object_vars($data);
		}

		foreach ($data as $key => $value) {
			if ($parent) {
				$new_key = sprintf('%s[%s]', $parent, $key);
			} else {
				$new_key = $key;
			}

			if (!$value instanceof \CURLFile && (is_array($value) || is_object($value))) {
				$result = array_merge($result, self::buildHTTPCurlQuery($value, $new_key));
			} else {
				$result[$new_key] = $value;
			}
		}

		return $result;
	}

	/**
	 * Send a cURL request
	 *
	 * @param \Hail\Browser\Method|string $method HTTP method to use
	 * @param string $url URL to send the request to
	 * @param mixed $body request body
	 * @param array $headers additional headers to send
	 *
	 * @throws \Hail\Browser\Exception if a cURL error occurs
	 * @return Response
	 */
	public static function send($method, $url, $body = null, $headers = [])
	{
		self::$handle = curl_init();

		$options = [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_HTTPHEADER => self::getFormattedHeaders($headers),
			CURLOPT_HEADER => true,
			CURLOPT_SSL_VERIFYPEER => self::$verifyPeer,
			//CURLOPT_SSL_VERIFYHOST accepts only 0 (false) or 2 (true). Future versions of libcurl will treat values 1 and 2 as equals
			CURLOPT_SSL_VERIFYHOST => self::$verifyHost === false ? 0 : 2,
			// If an empty string, '', is set, a header containing all supported encoding types is sent
			CURLOPT_ENCODING => '',
		];

		if ($method !== Method::GET) {
			$options[CURLOPT_POSTFIELDS] = $body;
		} else if ($method === Method::GET && is_array($body) && $body !== []) {
			if (strpos($url, '?') !== false) {
				$url .= '&';
			} else {
				$url .= '?';
			}

			$url .= urldecode(
				http_build_query(
					self::buildHTTPCurlQuery($body)
				)
			);
		}

		$options[CURLOPT_URL] = self::encodeUrl($url);

		if (self::$socketTimeout !== null) {
			$options[CURLOPT_TIMEOUT] = self::$socketTimeout;
		}

		if (self::$cookie) {
			$options[CURLOPT_COOKIE] = self::$cookie;
		}

		if (self::$cookieFile) {
			$options[CURLOPT_COOKIEFILE] = self::$cookieFile;
			$options[CURLOPT_COOKIEJAR] = self::$cookieFile;
		}

		if (!empty(self::$auth['user'])) {
			$options[CURLOPT_HTTPAUTH] = self::$auth['method'];
			$options[CURLOPT_USERPWD] = self::$auth['user'] . ':' . self::$auth['pass'];
		}

		if (self::$proxy['address'] !== false) {
			$options[CURLOPT_PROXYTYPE] = self::$proxy['type'];
			$options[CURLOPT_PROXY] = self::$proxy['address'];
			$options[CURLOPT_PROXYPORT] = self::$proxy['port'];
			$options[CURLOPT_HTTPPROXYTUNNEL] = self::$proxy['tunnel'];
			$options[CURLOPT_PROXYAUTH] = self::$proxy['auth']['method'];
			$options[CURLOPT_PROXYUSERPWD] = self::$proxy['auth']['user'] . ':' . self::$proxy['auth']['pass'];
		}

		curl_setopt_array(self::$handle, self::mergeCurlOptions($options, self::$curlOpts));

		$response = curl_exec(self::$handle);
		if ($error = curl_error(self::$handle)) {
			throw new Exception($error);
		}

		$info = self::getInfo();

		// Split the full response in its headers and body
		$header_size = $info['header_size'];
		$header = substr($response, 0, $header_size);
		$body = substr($response, $header_size);

		$return = new Response($info, $body, $header);
		curl_close(self::$handle);

		return $return;
	}

	public static function getInfo($opt = false)
	{
		if ($opt) {
			$info = curl_getinfo(self::$handle, $opt);
		} else {
			$info = curl_getinfo(self::$handle);
		}

		return $info;
	}

	public static function getCurlHandle()
	{
		return self::$handle;
	}

	public static function getFormattedHeaders($headers)
	{
		$formattedHeaders = [];

		$combinedHeaders = array_change_key_case(array_merge(self::$defaultHeaders, (array) $headers));

		foreach ($combinedHeaders as $key => $val) {
			$formattedHeaders[] = self::getHeaderString($key, $val);
		}

		if (!array_key_exists('user-agent', $combinedHeaders)) {
			$formattedHeaders[] = 'user-agent: hail-browser/2.0';
		}

		if (!array_key_exists('expect', $combinedHeaders)) {
			$formattedHeaders[] = 'expect:';
		}

		return $formattedHeaders;
	}

	private static function getArrayFromQuerystring($query)
	{
		$query = preg_replace_callback('/(?:^|(?<=&))[^=[]+/', function ($match) {
			return bin2hex(urldecode($match[0]));
		}, $query);

		parse_str($query, $values);

		return array_combine(array_map('hex2bin', array_keys($values)), $values);
	}

	/**
	 * Ensure that a URL is encoded and safe to use with cURL
	 *
	 * @param  string $url URL to encode
	 *
	 * @return string
	 */
	private static function encodeUrl($url)
	{
		$url_parsed = parse_url($url);

		$scheme = $url_parsed['scheme'] . '://';
		$host = $url_parsed['host'];
		$port = $url_parsed['port'] ?? null;
		$path = $url_parsed['path'] ?? null;
		$query = $url_parsed['query'] ?? null;

		if ($query !== null) {
			$query = '?' . http_build_query(self::getArrayFromQuerystring($query));
		}

		if ($port && $port[0] !== ':') {
			$port = ':' . $port;
		}

		return $scheme . $host . $port . $path . $query;
	}

	private static function getHeaderString($key, $val)
	{
		$key = trim(strtolower($key));

		return $key . ': ' . $val;
	}

	/**
	 * @param array $existing_options
	 * @param array $new_options
	 *
	 * @return array
	 */
	private static function mergeCurlOptions(&$existing_options, $new_options)
	{
		$existing_options = $new_options + $existing_options;

		return $existing_options;
	}
}
