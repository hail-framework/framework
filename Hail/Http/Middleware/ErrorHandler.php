<?php

namespace Hail\Http\Middleware;

use Hail\Http\Factory;
use Hail\Tracy\Debugger;
use Hail\Util\Json;
use Hail\Http\Exception\HttpErrorException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class ErrorHandler implements MiddlewareInterface
{
	/**
	 * @var string[][]
	 */
	private static $handlers = [
		'plain' => [
			'text/plain',
			'text/css',
			'text/javascript',
		],
		'jpeg' => [
			'image/jpeg',
		],
		'gif' => [
			'image/gif',
		],
		'png' => [
			'image/png',
		],
		'svg' => [
			'image/svg+xml',
		],
		'json' => [
			'application/json',
		],
		'xml' => [
			'text/xml',
		],
	];

	/**
	 * @var callable|null The status code validator
	 */
	private $statusCodeValidator;

	/**
	 * @var string The attribute name
	 */
	private $attribute = 'error';

	/**
	 * Configure the status code validator.
	 *
	 * @param callable $statusCodeValidator
	 *
	 * @return self
	 */
	public function statusCode(callable $statusCodeValidator)
	{
		$this->statusCodeValidator = $statusCodeValidator;

		return $this;
	}

	/**
	 * Set the attribute name to store the error info.
	 *
	 * @param string $attribute
	 *
	 * @return self
	 */
	public function attribute($attribute)
	{
		$this->attribute = $attribute;

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
	public function process(ServerRequestInterface $request, DelegateInterface $delegate)
	{
		ob_start();
		$level = ob_get_level();

		try {
			$response = $delegate->process($request);

			if ($this->isError($response->getStatusCode())) {
				$exception = new HttpErrorException($response->getReasonPhrase(), $response->getStatusCode());

				return $this->handleError($request, $exception);
			}

			return $response;
		} catch (HttpErrorException $exception) {
			return $this->handleError($request, $exception);
		} catch (\Throwable $exception) {
			if (PRODUCTION_MODE) {
				Debugger::log($exception, Debugger::EXCEPTION);

				return $this->handleError($request, HttpErrorException::create(500, [], $exception));
			}

			throw $exception;
		} finally {
			if (PRODUCTION_MODE) {
				while (ob_get_level() >= $level) {
					ob_end_clean();
				}
			}
		}
	}

	/**
	 * Execute the error handler.
	 *
	 * @param ServerRequestInterface $request
	 * @param HttpErrorException     $exception
	 *
	 * @return ResponseInterface
	 */
	private function handleError(ServerRequestInterface $request, HttpErrorException $exception)
	{
		return $this->handler(
			$request->withAttribute($this->attribute, $exception)
		);
	}

	/**
	 * Check whether the status code represents an error or not.
	 *
	 * @param int $statusCode
	 *
	 * @return bool
	 */
	private function isError($statusCode)
	{
		if ($this->statusCodeValidator) {
			return ($this->statusCodeValidator)($statusCode);
		}

		return $statusCode >= 400 && $statusCode < 600;
	}


	/**
	 * Execute the error handler.
	 *
	 * @param ServerRequestInterface $request
	 *
	 * @return ResponseInterface
	 */
	public function handler(ServerRequestInterface $request)
	{
		$error = $request->getAttribute('error');
		$accept = $request->getHeaderLine('Accept');

		$response = Factory::response($error->getCode());

		foreach (static::$handlers as $method => $types) {
			foreach ($types as $type) {
				if (stripos($accept, $type) !== false) {
					static::$method($error);

					return $response->withHeader('Content-Type', $type);
				}
			}
		}

		static::html($error);

		return $response->withHeader('Content-Type', 'text/html');
	}

	/**
	 * Output the error as plain text.
	 *
	 * @param HttpErrorException $error
	 */
	public static function plain(HttpErrorException $error)
	{
		echo "Error {$error->getCode()}\n{$error->getMessage()}";
	}

	/**
	 * Output the error as svg image.
	 *
	 * @param HttpErrorException $error
	 */
	public static function svg(HttpErrorException $error)
	{
		echo <<<EOT
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="50" viewBox="0 0 200 50">
    <text x="20" y="30" font-family="sans-serif" title="{$error->getMessage()}">
        Error {$error->getCode()}
    </text>
</svg>
EOT;
	}

	/**
	 * Output the error as html.
	 *
	 * @param HttpErrorException $error
	 */
	public static function html(HttpErrorException $error)
	{
		echo <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Error {$error->getCode()}</title>
    <style>html{font-family: sans-serif;}</style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h1>Error {$error->getCode()}</h1>
    {$error->getMessage()}
</body>
</html>
EOT;
	}

	/**
	 * Output the error as json.
	 *
	 * @param HttpErrorException $error
	 */
	public static function json(HttpErrorException $error)
	{
		echo Json::encode([
			'ret' => $error->getCode(),
			'msg' => $error->getMessage(),
			'HTTP_ERROR' => true,
		]);
	}

	/**
	 * Output the error as xml.
	 *
	 * @param HttpErrorException $error
	 */
	public static function xml(HttpErrorException $error)
	{
		echo <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<error>
    <code>{$error->getCode()}</code>
    <message>{$error->getMessage()}</message>
</error>
EOT;
	}

	/**
	 * Output the error as jpeg.
	 *
	 * @param HttpErrorException $error
	 */
	public static function jpeg(HttpErrorException $error)
	{
		$image = self::createImage($error);
		imagejpeg($image);
	}

	/**
	 * Output the error as gif.
	 *
	 * @param HttpErrorException $error
	 */
	public static function gif(HttpErrorException $error)
	{
		$image = self::createImage($error);
		imagegif($image);
	}

	/**
	 * Output the error as png.
	 *
	 * @param HttpErrorException $error
	 */
	public static function png(HttpErrorException $error)
	{
		$image = self::createImage($error);
		imagepng($image);
	}

	/**
	 * Creates a image resource with the error text.
	 *
	 * @param HttpErrorException $error
	 *
	 * @return resource
	 */
	private static function createImage(HttpErrorException $error)
	{
		$size = 200;
		$image = imagecreatetruecolor($size, $size);
		$textColor = imagecolorallocate($image, 255, 255, 255);
		imagestring($image, 5, 10, 10, "Error {$error->getCode()}", $textColor);
		foreach (str_split($error->getMessage(), (int) ($size / 10)) as $line => $text) {
			imagestring($image, 5, 10, ($line * 18) + 28, $text, $textColor);
		}

		return $image;
	}
}