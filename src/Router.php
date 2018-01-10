<?php
/**
 * @from https://github.com/baryshev/TreeRoute
 * Copyright (c) 2015, Vadim Baryshev
 */

namespace Hail;

use Hail\Util\OptimizeTrait;
use Hail\Util\Serialize;

/**
 * Class Router
 *
 * @package Hail
 * @author  Hao Feng <flyinghail@msn.com>
 *
 * @method head(string $route, array | callable $handler)
 * @method get(string $route, array | callable $handler)
 * @method post(string $route, array | callable $handler)
 * @method put(string $route, array | callable $handler)
 * @method patch(string $route, array | callable $handler)
 * @method delete(string $route, array | callable $handler)
 * @method pruge(string $route, array | callable $handler)
 * @method options(string $route, array | callable $handler)
 * @method trace(string $route, array | callable $handler)
 * @method connect(string $route, array | callable $handler)
 */
class Router
{
    use OptimizeTrait;

    private const PARAM_REGEXP = '/^{((([^:]+):(.+))|(.+))}$/';
    private const SEPARATOR_TRIM = "/ \t\n\r";
    private $routes = ['children' => [], 'regexps' => []];
    private $result = [];

    public function __construct(array $config)
    {
        $this->addRoutes($config);
    }

    /**
     * @param string $url
     *
     * @return array|null
     */
    private function match(string $url): ?array
    {
        $parts = \explode('?', $url, 2)[0];
        $parts = \explode('/', \trim($parts, static::SEPARATOR_TRIM));
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

            foreach ($current['regexps'] as $regexp => $route) {
                if (\preg_match($regexp, $v)) {
                    $current = $route;
                    $params[$current['name']] = $v;
                    continue 2;
                }
            }

            if (!isset($current['others'])) {
                return null;
            }

            $current = $current['others'];
            $params[$current['name']] = $v;
        }

        if (!isset($current['methods'])) {
            return null;
        }

        return [
            'methods' => $current['methods'],
            'route' => $current['route'],
            'params' => $params,
        ];
    }

    /**
     * @param array $config
     */
    protected function addRoutes(array $config): void
    {
        if (static::optimizeGet('hail-routes-sign') === $config) {
            $routes = static::optimizeGet('hail-routes');

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
            'hail-routes' => $this->routes,
            'hail-routes-sign' => $config,
        ]);
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
            $paramsMatch = \preg_match(static::PARAM_REGEXP, $v, $paramsMatches);
            if ($paramsMatch) {
                if (!empty($paramsMatches[2])) {
                    $paramsMatches[4] = '/^' . \preg_quote($paramsMatches[4], '/') . '$/';
                    if (!isset($current['regexps'][$paramsMatches[4]])) {
                        $current['regexps'][$paramsMatches[4]] = [
                            'children' => [],
                            'regexps' => [],
                            'name' => $paramsMatches[3],
                        ];
                    }
                    $current = &$current['regexps'][$paramsMatches[4]];
                } else {
                    if (!isset($current['others'])) {
                        $current['others'] = [
                            'children' => [],
                            'regexps' => [],
                            'name' => $paramsMatches[5],
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
            'HEAD' => true,
            'GET' => true,
            'POST' => true,
            'PUT' => true,
            'PATCH' => true,
            'DELETE' => true,
            'PURGE' => true,
            'OPTIONS' => true,
            'TRACE' => true,
            'CONNECT' => true,
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