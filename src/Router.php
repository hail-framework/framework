<?php
/**
 * @from https://github.com/baryshev/TreeRoute
 * Copyright (c) 2015, Vadim Baryshev
 */

namespace Hail;

use Hail\Http\RequestMethod;
use Hail\Util\OptimizeTrait;


/**
 * Class Router
 *
 * @package Hail
 * @author  Feng Hao <flyinghail@msn.com>
 *
 * @method head(string $route, array | callable $handler)
 * @method get(string $route, array | callable $handler)
 * @method post(string $route, array | callable $handler)
 * @method put(string $route, array | callable $handler)
 * @method patch(string $route, array | callable $handler)
 * @method delete(string $route, array | callable $handler)
 * @method purge(string $route, array | callable $handler)
 * @method options(string $route, array | callable $handler)
 * @method trace(string $route, array | callable $handler)
 * @method connect(string $route, array | callable $handler)
 */
class Router
{
    use OptimizeTrait;

    private const SEPARATOR_TRIM = "/ \t\n\r";

    /**
     * @var array[]
     */
    private $routes = ['children' => [], 'regexps' => []];

    /**
     * @var array
     */
    private $result = [];

    /**
     * @var string
     */
    private $prefix;

    public function __construct(array $config)
    {
        $this->refreshPrefix();
        $this->addRoutes($config);
    }

    private function refreshPrefix(): void
    {
        $save = true;
        if ($this->prefix === null) {
            $prefix = self::optimizeGet('prefix');
            if (empty($prefix)) {
                $prefix = '1';
            } else {
                $save = false;
            }
        } else {
            $prefix = (int) $this->prefix + 1;
        }

        $this->prefix = (string) $prefix;

        if ($save) {
            self::optimizeSet('prefix', $this->prefix);
        }
    }

    /**
     * @param string $url
     *
     * @return array|null
     */
    private function match(string $url): ?array
    {
        $path = \trim(\explode('?', $url, 2)[0], self::SEPARATOR_TRIM);

        $key = $this->prefix . '_' . $path;
        $result = self::optimizeGet($key);
        if (!empty($result)) {
            return $result;
        }

        $parts = \explode('/', $path);
        if (!isset($parts[1]) && $parts[0] === '') {
            $parts = [];
        }

        $params = [];
        $current = $this->routes;
        foreach ($parts as $v) {
            if (isset($current['children'][$v])) {
                $current = $current['children'][$v];
                continue;
            }

            if ($current['regexps'] !== []) {
                foreach ($current['regexps'] as $regexp => $route) {
                    if (\preg_match($regexp, $v)) {
                        $current = $route;
                        $params[$current['name']] = $v;
                        continue 2;
                    }
                }
            }

            if (!isset($current['others'])) {
                return null;
            }

            $current = $current['others'];
            $params[$current['name']] = $v;
        }

        $result = [
            'methods' => $current['methods'],
            'route' => $current['route'],
            'params' => $params,
        ];

        self::optimizeSet($key, $result);

        return $result;
    }

    /**
     * @param array $config
     */
    protected function addRoutes(array $config): void
    {
        if (static::optimizeGet('sign') === $config) {
            $routes = static::optimizeGet('routes');

            if (!empty($routes)) {
                $this->routes = $routes;

                return;
            }
        }

        foreach ($config as $app => $rules) {
            $app = \ucfirst($app);
            foreach ($rules as $route => $rule) {
                $handler = ['app' => $app];
                $methods = ['GET', 'POST'];

                if (\is_string($rule)) {
                    $route = $rule;
                } else {
                    $methods = $rule['methods'] ?? $methods;

                    foreach (['controller', 'action', 'params'] as $v) {
                        if (!empty($rule[$v])) {
                            $handler[$v] = $rule[$v];
                        }
                    }
                }

                $this->addRoute($methods, $route, $handler);
            }
        }

        static::optimizeSet([
            'routes' => $this->routes,
            'sign' => $config,
        ]);

        $this->refreshPrefix();
    }

    /**
     * @param array          $methods
     * @param string         $route
     * @param array|callable $handler
     *
     * @throws \InvalidArgumentException
     */
    public function addRoute(array $methods, string $route, $handler): void
    {
        if ($methods === []) {
            throw new \InvalidArgumentException('Methods is empty');
        }

        if (\is_callable($handler)) {
            $handler = \Closure::fromCallable($handler);
        } elseif (!\is_array($handler)) {
            throw new \InvalidArgumentException('Handler is not a valid type: ' . \var_export($handler, true));
        }

        $parts = \explode('/', \trim($route, static::SEPARATOR_TRIM));

        if (!isset($parts[1]) && $parts[0] === '') {
            $parts = [];
        }

        $current = &$this->routes;
        foreach ($parts as $v) {
            if ($v[0] === '{' && $v[-1] === '}') {
                $v = \substr($v, 1, -1);
                if (\strpos($v, ':') !== false) {
                    [$name, $regexp] = \explode(':', $v, 2);

                    $regexp = '/^' . \preg_quote($regexp, '/') . '$/';
                    if (!isset($current['regexps'][$regexp])) {
                        $current['regexps'][$regexp] = [
                            'children' => [],
                            'regexps' => [],
                            'name' => $name,
                        ];
                    }
                    $current = &$current['regexps'][$regexp];
                } else {
                    if (!isset($current['others'])) {
                        $current['others'] = [
                            'children' => [],
                            'regexps' => [],
                            'name' => $v,
                        ];
                    }
                    $current = &$current['others'];
                }
            } else {
                if (!isset($current['children'][$v])) {
                    $current['children'][$v] = [
                        'children' => [],
                        'regexps' => [],
                    ];
                }
                $current = &$current['children'][$v];
            }
        }

        $current['route'] = $route;

        if (!isset($current['methods'])) {
            $current['methods'] = [];
        }

        foreach ($methods as $v) {
            $current['methods'][\strtoupper($v)] = $handler;
        }
    }

    /**
     * @param string $url
     *
     * @return array|null
     */
    public function getOptions(string $url): ?array
    {
        $route = $this->match($url);
        if (!$route) {
            return null;
        }

        return \array_keys($route['methods']);
    }

    /**
     * @param string $method
     * @param string $url
     *
     * @return array
     */
    public function dispatch(string $method, string $url): array
    {
        $route = $this->match($url);

        $result = [
            'method' => $method,
            'url' => $url,
        ];

        if (!$route) {
            $result['error'] = 404;
        } elseif (isset($route['methods'][$method])) {
            $params = $route['params'];
            $handler = $route['methods'][$method];

            if (!$handler instanceof \Closure) {
                if (isset($handler['params'])) {
                    $params += $handler['params'];
                }

                $handler = [
                    'app' => $handler['app'] ?? $params['app'] ?? null,
                    'controller' => $handler['controller'] ?? $params['controller'] ?? null,
                    'action' => $handler['action'] ?? $params['action'] ?? null,
                ];
            }

            $result += [
                'route' => $route['route'],
                'params' => $params,
                'handler' => $handler,
            ];
        } else {
            $result += [
                'error' => 405,
                'route' => $route['route'],
                'params' => $route['params'],
                'allowed' => \array_keys($route['methods']),
            ];
        }

        return $this->result = $result;
    }

    /**
     * @return array
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->result['params'] ?? [];
    }

    /**
     * @return array|\Closure
     */
    public function getHandler()
    {
        return $this->result['handler'];
    }

    /**
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function __call($name, $arguments)
    {
        static $methods = [
            RequestMethod::HEAD => true,
            RequestMethod::GET => true,
            RequestMethod::POST => true,
            RequestMethod::PUT => true,
            RequestMethod::PATCH => true,
            RequestMethod::DELETE => true,
            RequestMethod::PURGE => true,
            RequestMethod::OPTIONS => true,
            RequestMethod::TRACE => true,
            RequestMethod::CONNECT => true,
        ];

        $method = \strtoupper($name);

        if (!isset($methods[$method])) {
            throw new \BadMethodCallException('Method not defined: ' . $method);
        }

        [$route, $handler] = $arguments;

        if (!\is_string($route)) {
            throw new \InvalidArgumentException('Route rule is not string: ' . \gettype($route));
        }

        $this->addRoute([$method], $route, $handler);
    }
}