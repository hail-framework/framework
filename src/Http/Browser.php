<?php

declare(strict_types=1);

namespace Hail\Http;

\defined('CURL_EXTENSION') || \define('CURL_EXTENSION', \extension_loaded('curl'));

use Hail\Http\Client\ClientInterface;
use Hail\Http\Client\Curl;
use Hail\Http\Client\Exception\ClientException;
use Hail\Http\Client\Exception\InvalidArgumentException;
use Hail\Http\Client\Exception\LogicException;
use Hail\Http\Client\FileGetContents;
use Hail\Http\Client\Middleware\MiddlewareInterface;
use Hail\Http\Client\MultiCurl;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Browser implements ClientInterface
{
    /** @var ClientInterface */
    private $client;

    /**
     * @var MiddlewareInterface[]
     */
    private $middlewares = [];

    /** @var RequestInterface */
    private $lastRequest;

    /** @var ResponseInterface */
    private $lastResponse;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (CURL_EXTENSION) {
            if (isset($config['multi']) && $config['multi']) {
                $client = new MultiCurl($config);
            } else {
                $client = new Curl($config);
            }
        } else {
            $client = new FileGetContents($config);
        }

        $this->client = $client;
    }

    public function get(string $url, array $headers = []): ResponseInterface
    {
        return $this->request(RequestMethod::GET, $url, $headers);
    }

    public function post(string $url, string $body = '', array $headers = []): ResponseInterface
    {
        return $this->request(RequestMethod::POST, $url, $headers, $body);
    }

    public function head(string $url, array $headers = []): ResponseInterface
    {
        return $this->request(RequestMethod::HEAD, $url, $headers);
    }

    public function patch(string $url, string $body = '', array $headers = []): ResponseInterface
    {
        return $this->request(RequestMethod::PATCH, $url, $headers, $body);
    }

    public function put(string $url, string $body = '', array $headers = []): ResponseInterface
    {
        return $this->request(RequestMethod::PUT, $url, $headers, $body);
    }

    public function delete(string $url, string $body = '', array $headers = []): ResponseInterface
    {
        return $this->request(RequestMethod::DELETE, $url, $headers, $body);
    }

    /**
     * Sends a request.
     *
     * @param string $method  The request method to use
     * @param string $url     The URL to call
     * @param array  $headers An array of request headers
     * @param string $body    The request content
     *
     * @return ResponseInterface The response object
     */
    public function request(string $method, string $url, array $headers = [], string $body = ''): ResponseInterface
    {
        $request = Factory::request($method, $url, $headers, $body);

        return $this->sendRequest($request);
    }

    /**
     * Submit a form.
     * [
     *  '*field*' => '*value*',
     *  '*file*' => [
     *      'path' => '*filepath*',
     *      'contentType' => '*plain/text*',
     *      'filename' => '*filename*',
     *  ]
     * ]
     *
     * @param string $url
     * @param array  $fields
     * @param string $method
     * @param array  $headers
     *
     * @return ResponseInterface
     *
     * @throws ClientException
     * @throws LogicException
     * @throws InvalidArgumentException
     */
    public function submitForm(
        string $url,
        array $fields,
        string $method = 'POST',
        array $headers = []
    ): ResponseInterface {
        $body = [];
        $files = '';
        $boundary = \uniqid('', true);
        foreach ($fields as $name => $field) {
            if (!isset($field['path'])) {
                $body[$name] = $field;
            } else {
                // This is a file
                $fileContent = \file_get_contents($field['path']);
                $files .= $this->prepareMultipart($name, $fileContent, $boundary, $field);
            }
        }

        if (empty($files)) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $body = \http_build_query($body);
        } else {
            $headers['Content-Type'] = 'multipart/form-data; boundary="' . $boundary . '"';

            foreach ($body as $name => $value) {
                $files .= $this->prepareMultipart($name, $value, $boundary);
            }
            $body = "$files--{$boundary}--\r\n";
        }

        $request = Factory::request($method, $url, $headers, $body);

        return $this->sendRequest($request);
    }

    /**
     * Send a PSR7 request.
     *
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return ResponseInterface
     * @throws ClientException
     * @throws LogicException
     * @throws InvalidArgumentException
     */
    public function sendRequest(RequestInterface $request, array $options = []): ResponseInterface
    {
        $chain = $this->createMiddlewareChain($this->middlewares,
            function (RequestInterface $request, callable $responseChain) use ($options) {
                $response = $this->client->sendRequest($request, $options);
                $responseChain($request, $response);
            }, function (RequestInterface $request, ResponseInterface $response) {
                $this->lastRequest = $request;
                $this->lastResponse = $response;
            });

        // Call the chain
        $chain($request);

        return $this->lastResponse;
    }

    /**
     * @param MiddlewareInterface[] $middlewares
     * @param callable              $requestChainLast
     * @param callable              $responseChainLast
     *
     * @return callable
     */
    private function createMiddlewareChain(
        array $middlewares,
        callable $requestChainLast,
        callable $responseChainLast
    ): callable {
        $responseChainNext = $responseChainLast;

        // Build response chain
        /** @var MiddlewareInterface $middleware */
        foreach ($middlewares as $middleware) {
            $lastCallable = function (RequestInterface $request, ResponseInterface $response) use (
                $middleware,
                $responseChainNext
            ) {
                return $middleware->handleResponse($request, $response, $responseChainNext);
            };

            $responseChainNext = $lastCallable;
        }

        $requestChainLast = function (RequestInterface $request) use ($requestChainLast, $responseChainNext) {
            // Send the actual request and get the response
            $requestChainLast($request, $responseChainNext);
        };

        $middlewares = \array_reverse($middlewares);

        // Build request chain
        $requestChainNext = $requestChainLast;
        /** @var MiddlewareInterface $middleware */
        foreach ($middlewares as $middleware) {
            $lastCallable = function (RequestInterface $request) use ($middleware, $requestChainNext) {
                return $middleware->handleRequest($request, $requestChainNext);
            };

            $requestChainNext = $lastCallable;
        }

        return $requestChainNext;
    }

    public function getLastRequest(): ?RequestInterface
    {
        return $this->lastRequest;
    }

    public function getLastResponse(): ?ResponseInterface
    {
        return $this->lastResponse;
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * Add a new middleware to the stack.
     *
     * @param MiddlewareInterface $middleware
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    private function prepareMultipart(string $name, string $content, string $boundary, array $data = []): string
    {
        $output = '';
        $fileHeaders = [];

        // Set a default content-disposition header
        $fileHeaders['Content-Disposition'] = sprintf('form-data; name="%s"', $name);
        if (isset($data['filename'])) {
            $fileHeaders['Content-Disposition'] .= sprintf('; filename="%s"', $data['filename']);
        }

        // Set a default content-length header
        if ($length = \strlen($content)) {
            $fileHeaders['Content-Length'] = (string)$length;
        }

        if (isset($data['contentType'])) {
            $fileHeaders['Content-Type'] = $data['contentType'];
        }

        // Add start
        $output .= "--$boundary\r\n";
        foreach ($fileHeaders as $key => $value) {
            $output .= sprintf("%s: %s\r\n", $key, $value);
        }
        $output .= "\r\n";
        $output .= $content;
        $output .= "\r\n";

        return $output;
    }
}
