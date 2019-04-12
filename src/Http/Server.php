<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Hail\Http;

use Hail\Container\Container;
use Hail\Http\Emitter\EmitterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * "Serve" incoming HTTP requests
 *
 * Given a callback, takes an incoming request, dispatches it to the
 * callback, and then sends a response.
 */
class Server
{
    /**
     * @var array
     */
    private $middleware;

    /**
     * Response emitter to use; by default, uses Emitter\Sapi.
     *
     * @var EmitterInterface
     */
    private $emitter;

    /**
     * @var ServerRequestInterface
     */
    private $request;


    /**
     * Constructor
     *
     * Given a callback, a request, and a response, we can create a server.
     *
     * @param array                  $middleware
     * @param ServerRequestInterface $request
     */
    public function __construct(array $middleware, ServerRequestInterface $request)
    {
        $this->middleware = $middleware;
        $this->request = $request;

        $this->reset();
    }

    public function reset()
    {
        $this->emitter = Emitter\Sapi::class;
    }

    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Set alternate response emitter to use.
     *
     * @param string $emitter
     */
    public function setEmitter(string $emitter): void
    {
        if (!\is_a($emitter, EmitterInterface::class, true)) {
            throw new \RuntimeException($emitter . ' is not a Hail\Http\Emitter\EmitterInterface.');
        }

        $this->emitter = $emitter;
    }

    /**
     * Create a Server instance
     *
     * Creates a server instance from the callback and the following
     * PHP environmental values:
     *
     * - server; typically this will be the $_SERVER superglobal
     * - query; typically this will be the $_GET superglobal
     * - body; typically this will be the $_POST superglobal
     * - cookies; typically this will be the $_COOKIE superglobal
     * - files; typically this will be the $_FILES superglobal
     *
     * @param array          $middleware
     * @param array          $server
     * @param array          $query
     * @param array          $body
     * @param array          $cookies
     * @param array          $files
     *
     * @return static
     */
    public static function createServer(
        array $middleware,
        array $server = null,
        array $query = null,
        array $body = null,
        array $cookies = null,
        array $files = null
    ) {
        $request = Helpers::createServer(
            $server, $query, $body, $cookies, $files
        );

        return new static($middleware, $request);
    }

    /**
     * Create a Server instance from an existing request object
     *
     * Provided a callback, an existing request object, and optionally an
     * existing response object, create and return the Server instance.
     *
     * @param array                  $middleware
     * @param ServerRequestInterface $request
     *
     * @return static
     */
    public static function createServerFromRequest(
        array $middleware,
        ServerRequestInterface $request
    ) {
        return new static($middleware, $request);
    }

    public function handler(Container $container = null): ResponseInterface
    {
        $dispatcher = new RequestHandler($this->middleware, $container);

        return $dispatcher->dispatch($this->request);
    }

    public function emit(ResponseInterface $response)
    {
        $this->emitter::emit($response);
    }

    /**
     * "Listen" to an incoming request
     *
     * @param Container $container
     */
    public function listen(Container $container = null)
    {
        $this->emit(
            $this->handler($container)
        );
    }
}