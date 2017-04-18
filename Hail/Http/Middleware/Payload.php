<?php

namespace Hail\Http\Middleware;

use Hail\Http\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\StreamInterface;
use Exception;


class Payload implements MiddlewareInterface
{
	/**
	 * @var callable[]
	 */
	protected $parsers = [
		'application/json' => ['self', 'jsonParser'],
		'application/x-www-form-urlencoded' => ['self', 'urlEncodeParser'],
		'text/csv' => ['self', 'csvParser'],
	];

	/**
	 * @var bool
	 */
	protected $override = false;

	/**
	 * @var string[]
	 */
	protected $methods = [
		'POST',
		'PUT',
		'PATCH',
		'DELETE',
		'COPY',
		'LOCK',
		'UNLOCK',
	];

	/**
	 * Add parser
	 *
	 * @param string   $contentType
	 * @param callable $parser
	 *
	 * @return self
	 */
	public function addParser(string $contentType, callable $parser): self
	{
		$this->parsers[$contentType] = \Closure::fromCallable($parser);

		return $this;
	}

	/**
	 * Configure the methods allowed.
	 *
	 * @param string[] $methods
	 *
	 * @return self
	 */
	public function methods(array $methods): self
	{
		$this->methods = $methods;

		return $this;
	}

	/**
	 * Configure if the parsed body overrides the previous value.
	 *
	 * @param bool $override
	 *
	 * @return self
	 */
	public function override(bool $override = true): self
	{
		$this->override = $override;

		return $this;
	}

	/**
	 * Process a server request and return a response.
	 *
	 * @param ServerRequestInterface $request
	 * @param DelegateInterface      $delegate
	 *
	 * @return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, DelegateInterface $delegate): ResponseInterface
	{
		if (($this->override || !$request->getParsedBody())
			&& in_array($request->getMethod(), $this->methods, true)
		) {
			$contentType = trim($request->getHeaderLine('Content-Type'));

			$parser = null;
			foreach ($this->parsers as $k => $v) {
				if (stripos($k, $contentType) === 0) {
					$parser = $v;
					break;
				}
			}

			if ($parser !== null) {
				try {
					$request = $request->withParsedBody($parser($request->getBody()));
				} catch (Exception $exception) {
					return Factory::response(400);
				}
			}
		}

		return $delegate->process($request);
	}

	/**
	 * Parse the url-encoded string.
	 *
	 * @param StreamInterface $stream
	 *
	 * @return array
	 */
	protected static function urlEncodeParser(StreamInterface $stream): array
	{
		parse_str((string) $stream, $data);

		return $data ?: [];
	}

	/**
	 * JSON parser.
	 *
	 * @param StreamInterface $stream
	 *
	 * @return array Returns an array when $assoc is true, and an object when $assoc is false
	 * @throws \DomainException
	 */
	protected static function jsonParser(StreamInterface $stream): array
	{
		$json = trim((string) $stream);
		if ($json === '') {
			return [];
		}

		$data = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new \DomainException(json_last_error_msg());
		}

		return $data ?: [];
	}

	/**
	 * Parses csv strings.
	 *
	 * @param StreamInterface $stream
	 *
	 * @return array
	 * @throws \RuntimeException
	 */
	public static function csvParser(StreamInterface $stream): array
	{
		if ($stream->isSeekable()) {
			$stream->rewind();
		}

		$handle = $stream->detach();
		$data = [];
		while (($row = fgetcsv($handle)) !== false) {
			$data[] = $row;
		}
		fclose($handle);

		return $data;
	}
}