<?php

declare(strict_types=1);

namespace Hail\Http;

use InvalidArgumentException;
use Psr\Http\Message\{
	ServerRequestInterface,
	StreamInterface,
	UploadedFileInterface,
	UriInterface
};

/**
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ServerRequest extends Request implements ServerRequestInterface
{
	/**
	 * @var array
	 */
	private $attributes = [];

	/**
	 * @var array
	 */
	private $cookieParams = [];

	/**
	 * @var null|array|object
	 */
	private $parsedBody;

	/**
	 * @var array
	 */
	private $queryParams = [];

	/**
	 * @var array
	 */
	private $serverParams;

	/**
	 * @var array
	 */
	private $uploadedFiles = [];

	/**
	 * @param string                               $method        HTTP method
	 * @param string|UriInterface                  $uri           URI
	 * @param array                                $headers       Request headers
	 * @param string|null|resource|StreamInterface $body          Request body
	 * @param string                               $version       Protocol version
	 * @param array                                $serverParams  Typically the $_SERVER superglobal
	 * @param array                                $cookies       Cookies for the message, if any.
	 */
	public function __construct(
		string $method,
		$uri,
		array $headers = [],
		$body = null,
		string $version = '1.1',
		array $serverParams = [],
		array $cookies = []
	)
	{
		$this->serverParams = $serverParams;
		$this->cookieParams = $cookies;

		if ($body === null) {
			$body = new PhpInputStream();
		}

		parent::__construct($method, $uri, $headers, $body, $version);
	}

	/**
	 * Return an UploadedFile instance array.
	 *
	 * @param array $files A array which respect $_FILES structure
	 *
	 * @throws InvalidArgumentException for unrecognized values
	 *
	 * @return array
	 */
	public static function normalizeFiles(array $files)
	{
		$normalized = [];

		foreach ($files as $key => $value) {
			if ($value instanceof UploadedFileInterface) {
				$normalized[$key] = $value;
			} elseif (is_array($value)) {
				if (isset($value['tmp_name'])) {
					$normalized[$key] = self::createUploadedFileFromSpec($value);
				} else {
					$normalized[$key] = self::normalizeFiles($value);
				}
			} else {
				throw new InvalidArgumentException('Invalid value in files specification');
			}
		}

		return $normalized;
	}

	/**
	 * Create and return an UploadedFile instance from a $_FILES specification.
	 *
	 * If the specification represents an array of values, this method will
	 * delegate to normalizeNestedFileSpec() and return that return value.
	 *
	 * @param array $value $_FILES struct
	 *
	 * @return array|UploadedFileInterface
	 * @throws InvalidArgumentException
	 */
	private static function createUploadedFileFromSpec(array $value)
	{
		if (is_array($value['tmp_name'])) {
			return self::normalizeNestedFileSpec($value);
		}

		return new UploadedFile(
			$value['tmp_name'],
			(int) $value['size'],
			(int) $value['error'],
			$value['name'],
			$value['type']
		);
	}

	/**
	 * Normalize an array of file specifications.
	 *
	 * Loops through all nested files and returns a normalized array of
	 * UploadedFileInterface instances.
	 *
	 * @param array $files
	 *
	 * @return UploadedFileInterface[]
	 * @throws InvalidArgumentException
	 */
	private static function normalizeNestedFileSpec(array $files = [])
	{
		$normalizedFiles = [];

		foreach ($files['tmp_name'] as $key => $v) {
			$spec = [
				'tmp_name' => $v,
				'size' => $files['size'][$key],
				'error' => $files['error'][$key],
				'name' => $files['name'][$key],
				'type' => $files['type'][$key],
			];
			$normalizedFiles[$key] = self::createUploadedFileFromSpec($spec);
		}

		return $normalizedFiles;
	}

	/**
	 * Return a ServerRequest populated with superglobals:
	 * $_GET
	 * $_POST
	 * $_COOKIE
	 * $_FILES
	 * $_SERVER.
	 *
	 * @return ServerRequestInterface
	 * @throws InvalidArgumentException
	 */
	public static function fromGlobals()
	{
		$serverRequest = Helpers::serverRequestFromArray($_SERVER, $_COOKIE);

		$serverRequest->queryParams = $_GET;
		$serverRequest->parsedBody = $_POST;
		$serverRequest->uploadedFiles = self::normalizeFiles($_FILES);

		return $serverRequest;
	}

	public function getServerParams()
	{
		return $this->serverParams;
	}

	public function getUploadedFiles()
	{
		return $this->uploadedFiles;
	}

	public function withUploadedFiles(array $uploadedFiles)
	{
		$new = clone $this;
		$new->uploadedFiles = $uploadedFiles;

		return $new;
	}

	public function getCookieParams()
	{
		return $this->cookieParams;
	}

	public function withCookieParams(array $cookies)
	{
		$new = clone $this;
		$new->cookieParams = $cookies;

		return $new;
	}

	public function getQueryParams()
	{
		return $this->queryParams;
	}

	public function withQueryParams(array $query)
	{
		$new = clone $this;
		$new->queryParams = $query;

		return $new;
	}

	public function getParsedBody()
	{
		return $this->parsedBody;
	}

	public function withParsedBody($data)
	{
		$new = clone $this;
		$new->parsedBody = $data;

		return $new;
	}

	public function getAttributes()
	{
		return $this->attributes;
	}

	public function getAttribute($attribute, $default = null)
	{
		if (!array_key_exists($attribute, $this->attributes)) {
			return $default;
		}

		return $this->attributes[$attribute];
	}

	public function withAttribute($attribute, $value)
	{
		$new = clone $this;
		$new->attributes[$attribute] = $value;

		return $new;
	}

	public function withoutAttribute($attribute)
	{
		if (!array_key_exists($attribute, $this->attributes)) {
			return $this;
		}

		$new = clone $this;
		unset($new->attributes[$attribute]);

		return $new;
	}
}
