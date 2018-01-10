<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Set header to default value if it does not exist.
 *
 * If a given header already exists the value wont be replaced and the request wont be changed.
 *
 * @author Soufiane Ghzal <sghzal@gmail.com>
 */
final class HeaderDefaultsPlugin implements PluginInterface
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
            if (!$request->hasHeader($header)) {
                $request = $request->withHeader($header, $headerValue);
            }
        }

        return $handler->handle($request);
    }
}
