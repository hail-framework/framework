<?php
/**
 * @from https://github.com/baryshev/TreeRoute
 * Copyright (c) 2015, Vadim Baryshev
 */

namespace Hail;

use Hail\Util\OptimizeTrait;
use Hail\Facade\Serialize;

/**
 * Class Router
 *
 * @package Hail
 * @author  Hao Feng <flyinghail@msn.com>
 */
class Router
{
    use OptimizeTrait;

    const PARAM_REGEXP = '/^{((([^:]+):(.+))|(.+))}$/';
    const SEPARATOR_TRIM = "/ \t\n\r";
    private $routes = ['children' => [], 'regexps' => []];
    private $result = [];

    public function __construct($config)
    {
        $this->addRoutes($config);
    }

    private function match($url)
    {
        $parts = explode('?', $url, 2)[0];
        $parts = explode('/', trim($parts, static::SEPARATOR_TRIM));
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
                if (preg_match($regexp, $v)) {
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
    protected function addRoutes($config)
    {
        $sign = sha1(Serialize::encode($config));
        $check = static::optimizeGet('routesSign');
        if ($check === $sign) {
            $this->routes = static::optimizeGet('routes');

            return;
        }

        foreach ($config as $app => $rules) {
            $app = ucfirst($app);
            foreach ($rules as $route => $rule) {
                $handler = ['app' => $app];
                $methods = ['GET', 'POST'];

                if (is_string($rule)) {
                    $route = $rule;
                } else {
                    $methods = $rule['methods'] ?? $methods;

                    if (!empty($rule['controller'])) {
                        $handler['controller'] = $rule['controller'];
                    }

                    if (!empty($rule['action'])) {
                        $handler['action'] = $rule['action'];
                    }
                }

                $this->addRoute($methods, $route, $handler);
            }
        }

        static::optimizeSet([
            'routes' => $this->routes,
            'routesSign' => $sign,
        ]);
    }

    /**
     * @param array          $methods
     * @param string         $route
     * @param array|callable $handler
     *
     * @throws \InvalidArgumentException
     */
    public function addRoute(array $methods, string $route, $handler)
    {
        if (is_callable($handler)) {
            $handler = \Closure::fromCallable($handler);
        } elseif (!is_array($handler)) {
            throw new \InvalidArgumentException('Handler is not a valid type: ' . var_export($handler, true));
        }

        $parts = explode('/', trim($route, static::SEPARATOR_TRIM));

        if (!isset($parts[1]) && $parts[0] === '') {
            $parts = [];
        }

        $current = &$this->routes;
        foreach ($parts as $v) {
            $paramsMatch = preg_match(static::PARAM_REGEXP, $v, $paramsMatches);
            if ($paramsMatch) {
                if (!empty($paramsMatches[2])) {
                    $paramsMatches[4] = '/^' . addcslashes($paramsMatches[4], '/') . '$/';
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
            $current['methods'][strtoupper($v)] = $handler;
        }
    }

    /**
     * @param string $url
     *
     * @return array|null
     */
    public function getOptions(string $url)
    {
        $route = $this->match($url);
        if (!$route) {
            return null;
        }

        return array_keys($route['methods']);
    }

    /**
     * @param string $method
     * @param string $url
     *
     * @return array
     */
    public function dispatch(string $method, string $url)
    {
        $route = $this->match($url);
        if (!$route) {
            return $this->result = [
                'error' => 404,
                'method' => $method,
                'url' => $url,
            ];
        }

        if (isset($route['methods'][$method])) {
            $params = $route['params'];
            $handler = $route['methods'][$method];

            if (!$handler instanceof \Closure) {
                $handler = [
                    'app' => $handler['app'] ?? $params['app'] ?? '',
                    'controller' => $handler['controller'] ?? $params['controller'] ?? '',
                    'action' => $handler['action'] ?? $params['action'] ?? '',
                ];

                unset($params['app'], $params['controller'], $params['action']);
            }

            return $this->result = [
                'method' => $method,
                'url' => $url,
                'route' => $route['route'],
                'params' => $params,
                'handler' => $handler,
            ];
        }

        return $this->result = [
            'error' => 405,
            'method' => $method,
            'url' => $url,
            'route' => $route['route'],
            'params' => $route['params'],
            'allowed' => array_keys($route['methods']),
        ];
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getRoutes()
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

        $method = strtoupper($name);

        if (!isset($methods[$method])) {
            throw new \BadMethodCallException('Method not defined: ' . $method);
        }

        [$route, $handler] = $arguments;

        if (!is_string($route)) {
            throw new \InvalidArgumentException('Route rule is not string: ' . gettype($route));
        }

        $this->addRoute([$method], $route, $handler);
    }
}