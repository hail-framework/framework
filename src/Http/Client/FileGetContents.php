<?php

declare(strict_types=1);

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\NetworkException;
use Hail\Http\Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class FileGetContents extends AbstractClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request, array $options = []): ResponseInterface
    {
        $options = $this->validateOptions($options);
        $context = \stream_context_create($this->getStreamContextArray($request, $options));

        $level = \error_reporting(0);
        $content = \file_get_contents($request->getUri()->__toString(), false, $context);
        \error_reporting($level);
        if (false === $content) {
            $error = \error_get_last();

            throw new NetworkException($request, $error['message']);
        }

        $response = Factory::response();
        $response = $this->parseHttpHeaders($response, (array) $http_response_header);
        $response->getBody()->write($content);
        $response->getBody()->rewind();

        return $response;
    }

    /**
     * Converts a request into an array for stream_context_create().
     *
     * @param RequestInterface $request A request object
     * @param array            $options
     *
     * @return array An array for stream_context_create()
     */
    protected function getStreamContextArray(RequestInterface $request, array $options): array
    {
        $headers = $request->getHeaders();
        unset($headers['Host']);
        $context = [
            'http' => [
                // values from the request
                'method' => $request->getMethod(),
                'header' => \implode("\r\n", $this->toHeaders($headers)),
                'content' => $request->getBody()->__toString(),
                'protocol_version' => $request->getProtocolVersion(),

                // values from the current client
                'ignore_errors' => true,
                'follow_location' => $options['allow_redirects'] && $options['max_redirects'] > 0,
                'max_redirects' => $options['max_redirects'] + 1,
                'timeout' => $options['timeout'],
            ],
            'ssl' => [
                'verify_peer' => $options['verify'],
                'verify_host' => $options['verify'],
            ],
        ];

        if (null !== $options['proxy']) {
            $context['http']['proxy'] = $options['proxy'];
            $context['http']['request_fulluri'] = true;
        }

        return $context;
    }
}
