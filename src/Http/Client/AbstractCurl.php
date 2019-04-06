<?php

declare(strict_types=1);

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\ClientException;
use Hail\Http\Client\Exception\NetworkException;
use Hail\Http\Client\Exception\RequestException;
use Hail\Http\Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base client class with helpers for working with cURL.
 */
abstract class AbstractCurl extends AbstractClient
{
    private $handles = [];

    private $maxHandles = 5;

    public function __construct(array $options = [])
    {
        static::$default['curl'] = [];
        static::$types['curl'] = 'array';

        parent::__construct($options);
    }

    /**
     * Creates a new cURL resource.
     *
     * @return resource A new cURL resource
     *
     * @throws ClientException If unable to create a cURL resource
     */
    protected function createHandle()
    {
        $curl = $this->handles ? \array_pop($this->handles) : \curl_init();
        if (false === $curl) {
            throw new ClientException('Unable to create a new cURL handle');
        }

        return $curl;
    }

    /**
     * Release a cUrl resource. This function is from Guzzle.
     *
     * @param resource $curl
     */
    protected function releaseHandle($curl): void
    {
        if (\count($this->handles) >= $this->maxHandles) {
            \curl_close($curl);
        } else {
            // Remove all callback functions as they can hold onto references
            // and are not cleaned up by curl_reset. Using curl_setopt_array
            // does not work for some reason, so removing each one
            // individually.
            \curl_setopt($curl, \CURLOPT_HEADERFUNCTION, null);
            \curl_setopt($curl, \CURLOPT_READFUNCTION, null);
            \curl_setopt($curl, \CURLOPT_WRITEFUNCTION, null);
            \curl_setopt($curl, \CURLOPT_PROGRESSFUNCTION, null);
            \curl_reset($curl);

            if (!\in_array($curl, $this->handles)) {
                $this->handles[] = $curl;
            }
        }
    }

    /**
     * Prepares a cURL resource to send a request.
     *
     * @param resource         $curl
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return ResponseInterface
     */
    protected function prepare($curl, RequestInterface $request, array $options): ResponseInterface
    {
        if (\defined('CURLOPT_PROTOCOLS')) {
            \curl_setopt($curl, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS);
            \curl_setopt($curl, \CURLOPT_REDIR_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS);
        }

        \curl_setopt($curl, \CURLOPT_HEADER, false);
        \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, false);
        \curl_setopt($curl, \CURLOPT_FAILONERROR, false);

        $this->setOptionsFromParameter($curl, $options);
        $this->setOptionsFromRequest($curl, $request);

        $response = Factory::response();
        \curl_setopt($curl, \CURLOPT_HEADERFUNCTION, function ($ch, $data) use ($response) {
            $str = \trim($data);
            if ('' !== $str) {
                if (0 === \stripos($str, 'http/')) {
                    $this->setStatus($response, $str);
                } else {
                    $this->addHeader($response, $str);
                }
            }

            return \strlen($data);
        });

        \curl_setopt($curl, \CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($response) {
            return $response->getBody()->write($data);
        });

        // apply additional options
        if ($options['curl'] !== []) {
            \curl_setopt_array($curl, $options['curl']);
        }

        return $response;
    }

    /**
     * Sets options on a cURL resource based on a request.
     *
     * @param resource         $curl    A cURL resource
     * @param RequestInterface $request A request object
     */
    private function setOptionsFromRequest($curl, RequestInterface $request): void
    {
        $options = [
            \CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            \CURLOPT_URL => $request->getUri()->__toString(),
            \CURLOPT_HTTPHEADER => $this->toHeaders($request->getHeaders()),
        ];

        if (0 !== $version = $this->getProtocolVersion($request)) {
            $options[\CURLOPT_HTTP_VERSION] = $version;
        }

        if ($request->getUri()->getUserInfo()) {
            $options[\CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
        }

        switch (strtoupper($request->getMethod())) {
            case 'HEAD':
                $options[\CURLOPT_NOBODY] = true;
                break;

            case 'GET':
                $options[\CURLOPT_HTTPGET] = true;
                break;

            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
            case 'OPTIONS':
                $body = $request->getBody();
                $bodySize = $body->getSize();
                if (0 !== $bodySize) {
                    if ($body->isSeekable()) {
                        $body->rewind();
                    }

                    // Message has non empty body.
                    if (null === $bodySize || $bodySize > 1024 * 1024) {
                        // Avoid full loading large or unknown size body into memory
                        $options[\CURLOPT_UPLOAD] = true;
                        if (null !== $bodySize) {
                            $options[\CURLOPT_INFILESIZE] = $bodySize;
                        }
                        $options[\CURLOPT_READFUNCTION] = function ($ch, $fd, $length) use ($body) {
                            return $body->read($length);
                        };
                    } else {
                        // Small body can be loaded into memory
                        $options[\CURLOPT_POSTFIELDS] = (string)$body;
                    }
                }
        }

        \curl_setopt_array($curl, $options);
    }

    /**
     * @param resource $curl
     * @param array    $options
     */
    private function setOptionsFromParameter($curl, array $options): void
    {
        if (null !== $proxy = $options['proxy']) {
            \curl_setopt($curl, \CURLOPT_PROXY, $proxy);
        }

        $canFollow = !\ini_get('safe_mode') && !\ini_get('open_basedir') && $options['allow_redirects'];
        \curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, $canFollow);
        \curl_setopt($curl, \CURLOPT_MAXREDIRS, $canFollow ? $options['max_redirects'] : 0);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYPEER, $options['verify'] ? 1 : 0);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYHOST, $options['verify'] ? 2 : 0);
        \curl_setopt($curl, \CURLOPT_TIMEOUT, $options['timeout']);
    }

    /**
     * @param RequestInterface $request
     * @param int              $errno
     * @param resource         $curl
     *
     * @throws NetworkException
     * @throws RequestException
     */
    protected function parseError(RequestInterface $request, int $errno, $curl): void
    {
        switch ($errno) {
            case \CURLE_OK:
                // All OK, create a response object
                break;
            case \CURLE_COULDNT_RESOLVE_PROXY:
            case \CURLE_COULDNT_RESOLVE_HOST:
            case \CURLE_COULDNT_CONNECT:
            case \CURLE_OPERATION_TIMEOUTED:
            case \CURLE_SSL_CONNECT_ERROR:
                throw new NetworkException($request, \curl_error($curl), $errno);
            default:
                throw new RequestException($request, \curl_error($curl), $errno);
        }
    }

    private function getProtocolVersion(RequestInterface $request): int
    {
        switch ($request->getProtocolVersion()) {
            case '1.0':
                return \CURL_HTTP_VERSION_1_0;
            case '1.1':
                return \CURL_HTTP_VERSION_1_1;
            case '2.0':
                if (\defined('CURL_HTTP_VERSION_2_0')) {
                    return \CURL_HTTP_VERSION_2_0;
                }

                throw new \UnexpectedValueException('libcurl 7.33 needed for HTTP 2.0 support');
            default:
                return 0;
        }
    }
}
