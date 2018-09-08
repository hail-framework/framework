<?php

namespace Hail\Http;

use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface,
    ServerRequestInterface,
    StreamInterface,
    UploadedFileInterface,
    UriInterface,
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
};
use Hail\Http\Message\{
    Uri, Request, ServerRequest, Response, Stream, UploadedFile
};

final class Factory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    /**
     * @param UriInterface|string $uri
     *
     * @return UriInterface
     */
    public static function uri($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        return new Uri($uri);
    }

    /**
     * @param string                      $method
     * @param string|UriInterface         $uri
     * @param array                       $headers
     * @param null|string|StreamInterface $body
     * @param string                      $protocolVersion
     *
     * @return RequestInterface
     */
    public static function request(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $protocolVersion = '1.1'
    ): RequestInterface {
        return new Request($method, $uri, $headers, $body, $protocolVersion);
    }

    public static function response(
        int $statusCode = 200,
        $body = null,
        array $headers = [],
        string $protocolVersion = '1.1',
        string $reasonPhrase = null
    ): ResponseInterface {
        return new Response($body, $statusCode, $headers, $protocolVersion, $reasonPhrase);
    }

    public static function serverRequest(
        $method,
        $uri = null,
        array $headers = [],
        $body = 'php://input',
        string $version = '1.1',
        array $serverParams = [],
        array $cookies = [],
        array $queryParams = [],
        array $parsedBody = [],
        array $uploadedFiles = []
    ): ServerRequestInterface {
        if (\is_array($method)) {
            return Helpers::createServer($method);
        }

        return new ServerRequest($method, $uri, $headers, $body, $version, $serverParams, $cookies, $queryParams,
            $parsedBody, $uploadedFiles);
    }

    /**
     * @param StreamInterface|resource|string|null $body
     *
     * @return StreamInterface
     */
    public static function stream($body = null): StreamInterface
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }

        if (\is_resource($body)) {
            return new Stream($body);
        }

        $resource = \fopen('php://temp', 'rw+b');
        $stream = new Stream($resource);
        if ($body) {
            $stream->write($body);
        }

        return $stream;
    }

    public static function streamFromFile(string $file, string $mode = 'r'): StreamInterface
    {
        return new Stream(\fopen($file, $mode));
    }

    /**
     * @param StreamInterface|string|resource $file
     * @param int|null                        $size
     * @param int                             $error
     * @param string|null                     $clientFilename
     * @param string|null                     $clientMediaType
     *
     * @return UploadedFileInterface
     */
    public static function uploadedFile(
        $file,
        int $size = null,
        int $error = \UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ): UploadedFileInterface {
        if ($size === null) {
            if ($file instanceof StreamInterface) {
                $size = $file->getSize();
            } elseif (\is_resource($file)) {
                $stats = \fstat($file);
                $size = $stats['size'];
            } else {
                $size = \filesize($file);
            }
        }

        return new UploadedFile($file, $size, $error, $clientFilename, $clientMediaType);
    }

    public function createRequest(string $method, $uri): RequestInterface
    {
        return self::request($method, $uri);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return self::response($code, null, [], '1.1', $reasonPhrase);
    }

    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return self::serverRequest($method, $uri, [], $body = 'php://input', '1.1', $serverParams);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return self::stream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return self::streamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }

    public function createUploadedFile(
        StreamInterface $stream,
        int $size = null,
        int $error = \UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ): UploadedFileInterface {
        return self::uploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return self::uri($uri);
    }
}