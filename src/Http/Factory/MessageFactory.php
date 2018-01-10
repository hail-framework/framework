<?php

declare(strict_types=1);

namespace Hail\Http\Factory;

use Interop\Http\Factory\{
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
};
use Hail\Http\Factory;

/**
 * @author Hao Feng <flyinghail@msn.com>
 */
class MessageFactory implements
	RequestFactoryInterface,
	ResponseFactoryInterface,
	ServerRequestFactoryInterface,
	StreamFactoryInterface,
	UploadedFileFactoryInterface,
	UriFactoryInterface
{
	public function createRequest($method, $uri)
	{
		return Factory::request($method, $uri);
	}

	public function createResponse($code = 200)
	{
		return Factory::response((int) $code);
	}

	public function createServerRequest($method = null, $uri = null)
	{
		return Factory::serverRequest($method, $uri);
	}

	public function createServerRequestFromArray(array $server)
	{
		return Factory::serverRequest($server);
	}

	public function createStream($body = null)
	{
		return Factory::stream($body ?? '');
	}

	public function createStreamFromFile($file, $mode = 'r')
	{
		return Factory::streamFromFile($file, $mode);
	}

	public function createStreamFromResource($resource)
	{
		return Factory::stream($resource);
	}

	public function createUploadedFile(
		$file,
		$size = null,
		$error = \UPLOAD_ERR_OK,
		$clientFilename = null,
		$clientMediaType = null
	)
	{
		return Factory::uploadedFile($file, $size, $error, $clientFilename, $clientMediaType);
	}

	public function createUri($uri = '')
	{
		return Factory::uri($uri);
	}
}
