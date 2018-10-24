<?php

namespace Hail;

use Hail\Container\{
    Container, Reflection
};
use Hail\Http\{
    Server, Response
};
use Hail\Exception\{
    ActionForward, BadRequestException
};
use Hail\Optimize\OptimizeTrait;
use Hail\Util\Builder;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

class Application
{
    use OptimizeTrait;

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
     * @param array|\Closure $handler
     * @param array|null     $params
     *
     * @return ResponseInterface
     *
     * @throws BadRequestException
     * @throws ActionForward when forward to another action
     */
    public function handle($handler = null, array $params = null): ResponseInterface
    {
        $handler = $this->handler($handler);
        $params = $this->params($params);

        $parameters = null;
        if (!$handler instanceof \Closure) {
            [$class, $method] = $this->convert($handler);

            if ($method === null) {
                $key = $handler = $class;
            } else {
                $controller = new $class;
                $handler = [$controller, $method];
                $key = "{$class}::{$method}";
            }

            $parameters = self::optimizeGet($key);
            if ($parameters === false) {
                $parameters = Builder::getCallableParameters($handler, true);
                self::optimizeSet($key, $parameters);
            }
        }

        $result = $this->container->call($handler, $params, $parameters);

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

        if (\function_exists($actionClass)) {
            $class = $actionClass;
            $method = null;
        } elseif (\is_a($actionClass, Action::class, true)) {
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
     * @param array|\Closure|null $handler
     *
     * @return array|\Closure
     */
    public function handler($handler = null)
    {
        if ($handler !== null) {
            $this->handler = $this->getRealHandler($handler);
        }

        return $this->handler;
    }

    /**
     * @param array|\Closure $handler
     *
     * @return array|\Closure
     */
    public function getRealHandler($handler)
    {
        if ($handler instanceof \Closure) {
            return $handler;
        }

        $default = \is_array($this->handler) ? $this->handler : static::$default;

        return $handler + $default;
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