<?php

namespace Hail;

use Hail\Container\Container;
use Hail\Exception\ActionForward;
use Hail\Exception\ActionError;
use Hail\Http\{
    Server,
    Response
};
use Hail\Exception\BadRequestException;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

class Application
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var array|\Closure
     */
    protected $handler;

    protected static $default = [
        'app' => null,
        'controller' => 'Index',
        'action' => 'index',
    ];

    /**
     * Application constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function reset()
    {
        if ($this->server) {
            $this->server->reset();
        }

        $this->get('response')->reset();
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->container->get($name);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function config(string $name)
    {
        return $this->get('config')->get($name);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return Server
     */
    public function createServer(ServerRequestInterface $request): Server
    {
        if ($this->server === null) {
            $this->server = Server::createServerFromRequest(
                $this->config('middleware'),
                $request
            );
        } else {
            $this->server->setRequest($request);
        }

        $this->setRequest($request);

        return $this->server;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        if ($this->server === null) {
            $this->server = Server::createServer(
                $this->config('middleware'),
                $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
            );

            $this->setRequest(
                $this->server->getRequest()
            );
        }

        return $this->server;
    }

    public function listen(): void
    {
        $server = $this->server ?? $this->getServer();
        $server->listen($this->container);
    }

    /**
     * Set alternate response emitter to use.
     *
     * @param string $emitter
     * @param string $streamEmitter
     */
    public function emitter(string $emitter, string $streamEmitter = null): void
    {
        $server = $this->server ?? $this->getServer();
        $server->setEmitter($emitter);

        $streamEmitter && $this->get('response')->setEmitter($streamEmitter);
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->get('request')->setServerRequest($request);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return array
     * @throws BadRequestException
     */
    public function dispatch(ServerRequestInterface $request)
    {
        $result = $request->getAttribute('routing');

        if (isset($result['error'])) {
            throw new BadRequestException('Router not found', $result['error']);
        }

        $this->setRequest($request->withoutAttribute('routing'));
        $this->params($result['params'] ?? []);

        return $this->handler($result['handler']);
    }

    /**
     * @param array|\Closure $handler
     *
     * @return ResponseInterface
     *
     * @throws BadRequestException
     * @throws ActionForward when forward to another action
     */
    public function handle($handler): ResponseInterface
    {
        if (!$handler instanceof \Closure) {
            [$class, $method] = $this->convert($handler);
            $controller = $this->container->build($class);

            $handler = [$controller, $method];
        }

        $result = $this->container->call($handler);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        /** @var Response $response */
        $response = $this->get('response');

        return $response->default($result);
    }

    /**
     * @param array $handler
     *
     * @return array
     * @throws BadRequestException
     */
    protected function convert(array $handler): array
    {
        $class = $this->class($handler);

        $action = $handler['action'] ?? 'index';
        $actionClass = $class . '\\' . \ucfirst($action);

        if (\is_a($actionClass, Action::class, true)) {
            $class = $actionClass;
            $method = '__invoke';
        } elseif (\is_a($class, Controller::class, true)) {
            $method = \lcfirst($action) . 'Action';

            if (!\method_exists($class, $method)) {
                throw new BadRequestException("Action not defined: {$class}::{$method}", 404);
            }
        } else {
            throw new BadRequestException("Controller not defined: {$class}", 404);
        }

        return [$class, $method];
    }

    /**
     * @param array $handler
     *
     * @return string
     */
    protected function class(array $handler): string
    {
        $namespace = '\App\Controller';

        if ($app = $handler['app']) {
            $namespace .= '\\' . \ucfirst($app);
        }

        $class = $handler['controller'] ?? 'Index';

        return \strpos($class, $namespace) === 0 ? $class : $namespace . '\\' . \ucfirst($class);
    }

    /**
     * Get param from router
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed|null
     */
    public function param(string $name, $value = null)
    {
        if ($value === null) {
            return $this->params[$name] ?? null;
        }

        return $this->params[$name] = $value;
    }

    /**
     * Get all params from router
     *
     * @param array|null $array
     *
     * @return array
     */
    public function params(array $array = null): array
    {
        if ($array === null) {
            return $this->params;
        }

        return $this->params = $array;
    }

    /**
     * @param array|null $handler
     *
     * @return array|\Closure
     */
    public function handler(array $handler = null)
    {
        if ($handler !== null) {
            if ($handler instanceof \Closure) {
                $this->handler = $handler;
            } else {
                $default = \is_array($this->handler) ? $this->handler : static::$default;
                $this->handler = $handler + $default;
            }
        }

        return $this->handler;
    }

    public function render(ResponseInterface $response, string $name, array $params = []): ResponseInterface
    {
        /** @var Template\Engine $template */
        $template = $this->get('template');

        $response->getBody()->write(
            $template->capture($name, $params)
        );

        return $response;
    }
}