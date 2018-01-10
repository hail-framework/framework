<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Append headers to the request.
 *
 * If the header already exists the value will be appended to the current value.
 *
 * This only makes sense for headers that can have multiple values like 'Forwarded'
 *
 * @see https://en.wikipedia.org/wiki/List_of_HTTP_header_fields
 *
 * @author Soufiane Ghzal <sghzal@gmail.com>
 */
final class HeaderAppendPlugin implements PluginInterface
{
    /**
     * @var array
     */
    private $headers;

    /**
     * @param array $headers Hashmap of header name to header value
     */
    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        foreach ($this->headers as $header => $headerValue) {
            $request = $request->withAddedHeader($header, $headerValue);
        }

        return $handler->handle($request);
    }
}
