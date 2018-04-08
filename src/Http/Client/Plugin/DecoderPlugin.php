<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Hail\Http\Client\Message\Encoding;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

\defined('ZLIB_EXTENSION') || \define('ZLIB_EXTENSION', \extension_loaded('zlib'));

/**
 * Allow to decode response body with a chunk, deflate, compress or gzip encoding.
 *
 * If zlib is not installed, only chunked encoding can be handled.
 *
 * If Content-Encoding is not disabled, the plugin will add an Accept-Encoding header for the encoding methods it supports.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class DecoderPlugin implements PluginInterface
{
    /**
     * @var bool Whether this plugin decode stream with value in the Content-Encoding header (default to true).
     *
     * If set to false only the Transfer-Encoding header will be used
     */
    private $useContentEncoding = true;

    /**
     * @param array $config {
     *
     *    @var bool $use_content_encoding Whether this plugin should look at the Content-Encoding header first or only at the Transfer-Encoding (defaults to true).
     * }
     */
    public function __construct(array $config = [])
    {
        if (isset($config['use_content_encoding'])) {
            $this->useContentEncoding = (bool) $config['use_content_encoding'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        $encodings = ZLIB_EXTENSION ? ['gzip', 'deflate'] : ['identity'];

        if ($this->useContentEncoding) {
            $request = $request->withHeader('Accept-Encoding', $encodings);
        }
        $encodings[] = 'chunked';
        $request = $request->withHeader('TE', $encodings);

        return $handler->handle($request)->then(function (ResponseInterface $response) {
            return $this->decodeResponse($response);
        });
    }

    /**
     * Decode a response body given its Transfer-Encoding or Content-Encoding value.
     *
     * @param ResponseInterface $response Response to decode
     *
     * @return ResponseInterface New response decoded
     */
    private function decodeResponse(ResponseInterface $response)
    {
        $response = $this->decodeOnEncodingHeader('Transfer-Encoding', $response);

        if ($this->useContentEncoding) {
            $response = $this->decodeOnEncodingHeader('Content-Encoding', $response);
        }

        return $response;
    }

    /**
     * Decode a response on a specific header (content encoding or transfer encoding mainly).
     *
     * @param string            $headerName Name of the header
     * @param ResponseInterface $response   Response
     *
     * @return ResponseInterface A new instance of the response decoded
     */
    private function decodeOnEncodingHeader($headerName, ResponseInterface $response)
    {
        if ($response->hasHeader($headerName)) {
            $encodings = $response->getHeader($headerName);
            $newEncodings = [];

            while ($encoding = \array_pop($encodings)) {
                $stream = $this->decorateStream($encoding, $response->getBody());

                if (false === $stream) {
                    \array_unshift($newEncodings, $encoding);

                    continue;
                }

                $response = $response->withBody($stream);
            }

            $response = $response->withHeader($headerName, $newEncodings);
        }

        return $response;
    }

    /**
     * Decorate a stream given an encoding.
     *
     * @param string          $encoding
     * @param StreamInterface $stream
     *
     * @return StreamInterface|false A new stream interface or false if encoding is not supported
     */
    private function decorateStream($encoding, StreamInterface $stream)
    {
        switch (\strtolower($encoding)) {
            case 'chunked':
                return new Encoding\DechunkStream($stream);

            case 'deflate':
                return new Encoding\DecompressStream($stream);

            case 'gzip':
                return new Encoding\GzipDecodeStream($stream);

            default:
                return false;
        }
    }
}
