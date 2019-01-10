<?php

namespace Hail\Http\Middleware;

use Hail\Http\Factory;
use Hail\Http\Helpers;
use Psr\Http\{
    Server\MiddlewareInterface,
    Server\RequestHandlerInterface,
    Message\ServerRequestInterface,
    Message\ResponseInterface,
    Message\StreamInterface
};


class Payload implements MiddlewareInterface
{
	/**
	 * @var callable[]
	 */
	protected $parsers = [
		'application/json' => [self::class, 'jsonParser'],
		'application/x-www-form-urlencoded' => [self::class, 'urlEncodeParser'],
		'multipart/form-data' => [self::class, 'multipartParser'],
		'text/csv' => [self::class, 'csvParser'],
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
	 * @param RequestHandlerInterface      $handler
	 *
	 * @return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		if (($this->override || !$request->getParsedBody())
			&& \in_array($request->getMethod(), $this->methods, true)
		) {
			$contentType = \trim($request->getHeaderLine('Content-Type'));

			$parser = null;
			foreach ($this->parsers as $k => $v) {
				if (\stripos($k, $contentType) === 0) {
					$parser = $v;
					break;
				}
			}

			if ($parser !== null) {
				try {
					$data = $parser($request->getBody(), $contentType);

					if (isset($data['body'])) {
						$request = $request->withParsedBody($data['body']);
					}

					if (isset($data['files'])) {
						$request = $request->withUploadedFiles($data['files']);
					}
				} catch (\Exception $exception) {
					return Factory::response(400);
				}
			}
		}

		return $handler->handle($request);
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
		\parse_str((string) $stream, $data);

		return ['body' => $data ?: []];
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
		$json = \trim((string) $stream);
		if ($json === '') {
			return [];
		}

		$data = \json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
		if (\json_last_error() !== JSON_ERROR_NONE) {
			throw new \DomainException(\json_last_error_msg());
		}

		return ['body' => $data ?: []];
	}

	/**
	 * Parses csv strings.
	 *
	 * @param StreamInterface $stream
	 *
	 * @return array
	 * @throws \RuntimeException
	 */
	protected static function csvParser(StreamInterface $stream): array
	{
		if ($stream->isSeekable()) {
			$stream->rewind();
		}

		$handle = $stream->detach();
		$data = [];
		while (($row = \fgetcsv($handle)) !== false) {
			$data[] = $row;
		}
		\fclose($handle);

		return ['body' => $data];
	}

	protected static function multipartParser(StreamInterface $stream, string $contentType): array
	{
		$boundary = '--';
		if (\preg_match('/boundary\s*=\s*(.*)$/', $contentType, $matches)) {
			$boundary .= $matches[1];
		}

		if ($stream->isSeekable()) {
			$stream->rewind();
		}

		$body = $files = [];
		$prefix = $tail = '';
		while (true) {
			$head = '';

			if (!self::findBoundary($stream, $head, "\r\n\r\n", $next, $prefix)) {
				break;
			}

			$header = self::parseHeader($head);
			if (
				!isset($header['Content-Disposition']) ||
				$header['Content-Disposition']['value'] !== 'form-data' ||
				empty($header['Content-Disposition']['options'])
			) {
				break;
			}

			$options = $header['Content-Disposition']['options'];

			if (isset($options['filename'])) {
				$filename = \tempnam(\storage_path('temp'), 'http_upload_');
				$handle = \fopen($filename, 'wb');

                if (!self::findBoundary($stream, $handle, $boundary, $prefix, $next)) {
                    throw new \LogicException('Error parsing multipart/form-data. No boundary.');
                }

                $files[$options['name']] = Factory::uploadedFile(
                    $filename,
                    \filesize($filename),
                    UPLOAD_ERR_OK,
                    $options['filename'],
                    $header['Content-Type']['value'] ?? null
                );

                \fclose($handle);
			} else {
                $handle = '';
                if (!self::findBoundary($stream, $handle, $boundary, $prefix, $next)) {
                    throw new \LogicException('Error parsing multipart/form-data. No boundary.');
                }

                $body[$options['name']] = \rawurldecode($handle);
			}
		}

		return [
			'body' => $body,
			'files' => $files,
		];
	}

	protected static function parseHeader($head): array
	{
		$return = [];
		foreach (\explode("\r\n", $head) as $line) {
			if (\trim($line) === '') {
				break;
			}

			[$name, $value] = \explode(':', $line, 2);

			$name = Helpers::normalizeHeaderName($name);
			[$value, $options] = Helpers::parseHeaderValue($value);

			$return[$name] = [
				'value' => $value,
				'options' => $options
			];
		}

		return $return;
	}

	protected static function findBoundary(StreamInterface $in, &$out, $boundary, &$tail, $prev = '')
	{
		$tail = '';
		$end = true;

		while ($chunk = $in->read(4096)) {
			$end = false;

			if (self::doFindBoundary($prev, $chunk, $boundary, $out, $tail)) {
				return true;
			}

			$prev = $chunk;
		}

		if ($end) {
			return self::doFindBoundary($prev, '', $boundary, $out, $tail);
		}

		return false;
	}

	protected static function doFindBoundary(string $prev, string $chunk, string $boundary, &$out, &$tail)
	{
		$chunk = $prev . $chunk;

		$return = false;
		if (($pos = \strpos($chunk, $boundary)) !== false) {
			$prev = (string) \substr($chunk, 0, $pos);
			$tail = (string) \substr($chunk, $pos + \strlen($boundary));
			$return = true;
		}

		if (\is_string($out)) {
			$out .= $prev;
		} else {
			\fwrite($out, $prev);
		}

		return $return;
	}
}