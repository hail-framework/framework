<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * A plugin is a middleware to transform the request and/or the response.
 *
 * The plugin can:
 *  - break the chain and return a response
 *  - dispatch the request to the next middleware
 *  - restart the request
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
interface PluginInterface
{
    /**
     * Handle the request and return the response coming from the next callable.
     *
     * @see http://docs.php-http.org/en/latest/plugins/build-your-own.html
     *
     * @param RequestInterface        $request
     * @param RequestHandlerInterface $handler
     *
     * @return PromiseInterface Resolves a PSR-7 Response or fails with an Hail\Http\Client\Exception (The same as HttpAsyncClient).
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface;
}
