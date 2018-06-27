<?php

namespace Hail\Http\Middleware;

use Hail\Application;
use Hail\Exception\ActionForward;
use Hail\Exception\BadRequestException;
use Hail\Http\Exception\HttpErrorException;
use Psr\Http\{
    Server\MiddlewareInterface,
    Server\RequestHandlerInterface,
    Message\ServerRequestInterface,
    Message\ResponseInterface
};

class Handler implements MiddlewareInterface
{
    /**
     * @var Application
     */
    protected $app;

    protected $count = 0;
    protected $max;

    /**
     * @param Application $app
     * @param int         $max
     */
    public function __construct(Application $app, $max = null)
    {
        $this->app = $app;
        $this->max = $max ?: 30;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws HttpErrorException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $request->getAttribute('routing');

        if (isset($result['error'])) {
            throw HttpErrorException::create($result['error'], [
                'code' => $result['error'],
                'message' => 'Routing not found',
            ]);
        }

        $this->count = 0;
        $this->app->setRequest($request->withoutAttribute('routing'));

        return $this->handle($result['handler'], $result['params']);
    }

    /**
     * @param array|\Closure      $handler
     * @param array|null $params
     *
     * @return ResponseInterface
     * @throws HttpErrorException
     */
    protected function handle($handler, array $params = null)
    {
        ++$this->count;
        if ($this->count > $this->max) {
            throw HttpErrorException::create(500, [
                'code' => 500,
                'message' => 'Action forward is too much',
            ]);
        }

        try {
            return $this->app->handle($handler, $params);
        } catch (ActionForward $e) {
            return $this->handle($e->getHandler(), $e->getParams());
        } catch (BadRequestException $e) {
            throw HttpErrorException::create($e->getCode(), [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ], $e);
        }
    }
}