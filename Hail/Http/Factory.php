<?php

namespace Hail\Http;

use Psr\Http\Message\{
	StreamInterface,
	UriInterface
};

final class Factory
{
	/**
	 * @param UriInterface|string $uri
	 *
	 * @return UriInterface
	 */
	public static function uri($uri)
	{
		if ($uri instanceof UriInterface) {
			return $uri;
		}

		return new Uri($uri);
	}

	public static function request(
		string $method,
		$uri,
		array $headers = [],
		$body = null,
		string $protocolVersion = '1.1'
	)
	{
		return new Request($method, $uri, $headers, $body, $protocolVersion);
	}

	public static function response(
		int $statusCode = 200,
		string $reasonPhrase = null,
		array $headers = [],
		$body = null,
		string $protocolVersion = '1.1'
	)
	{
		return new Response($statusCode, $headers, $body, $protocolVersion, $reasonPhrase);
	}

	public static function serverRequest($method, $uri = null)
	{
		if (is_array($method)) {
			$server = $method;

			$method = Helpers::getMethod($server);

			if (!isset($server['HTTPS'])) {
				$server['HTTPS'] = 'off';
			}
			$uri = Uri::fromArray($server);

			$headers = Helpers::getHeaders($server);
			$protocol = Helpers::getProtocol($server);

			return new ServerRequest($method, $uri, $headers, null, $protocol, $server);
		}

		$uri = self::uri($uri);

		return new ServerRequest($method, $uri);
	}

	/**
	 * @param StreamInterface|resource|string|null $body
	 *
	 * @return StreamInterface
	 */
	public static function stream($body = null)
	{
		if ($body instanceof StreamInterface) {
			return $body;
		}

		if (is_resource($body)) {
			return new Stream($body);
		}

		$resource = fopen('php://temp', 'rw+b');
		$stream = new Stream($resource);
		if ($body) {
			$stream->write($body);
		}

		return $stream;
	}

	public static function streamFromFile($file, $mode = 'r')
	{
		$resource = fopen($file, $mode);

		return new Stream($resource);
	}

	public static function uploadedFile(
		$file,
		int $size = null,
		int $error = \UPLOAD_ERR_OK,
		string $clientFilename = null,
		string $clientMediaType = null
	)
	{
		if ($size === null) {
			if (is_string($file)) {
				$size = filesize($file);
			} else {
				$stats = fstat($file);
				$size = $stats['size'];
			}
		}

		return new UploadedFile($file, $size, $error, $clientFilename, $clientMediaType);
	}
}