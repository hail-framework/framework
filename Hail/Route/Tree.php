<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/6/24 0024
 * Time: 18:11
 * @from https://github.com/baryshev/TreeRoute
 */

namespace Hail\Route;

/**
 * Copyright (c) 2015, Vadim Baryshev
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the
 * names of its contributors may be used to endorse or promote products
 * derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Class Tree
 * @package Hail\Route
 */
class Tree
{
    const PARAM_REGEXP = '/^{((([^:]+):(.+))|(.+))}$/';
    const SEPARATOR_REGEXP = '/^[\s\/]+|[\s\/]+$/';
    private $routes = ['childs' => [], 'regexps' => []];

    private function match($url)
    {
        $parts = explode('?', $url, 1);
        $parts = explode('/', preg_replace(static::SEPARATOR_REGEXP, '', $parts[0]));
        $length = sizeof($parts);

        $params = [];
        $current = $this->routes;

        if ($length === 1 && $parts[0] === '') {
        } else {
            for ($i = 0; $i < $length; $i++) {
                if (isset($current['childs'][$parts[$i]])) {
                    $current = $current['childs'][$parts[$i]];
                } else {
                    foreach ($current['regexps'] as $regexp => $route) {
                        if (preg_match('/' . addcslashes($regexp, '/') . '/', $parts[$i])) {
                            $current = $route;
                            $params[$current['name']] = $parts[$i];
                            continue 2;
                        }
                    }
                    if (isset($current['others'])) {
                        $current = $current['others'];
                        $params[$current['name']] = $parts[$i];
                    } else {
                        return null;
                    }
                }
            }
        }

        if (!isset($current['methods'])) {
            return null;
        } else {
            return [
                'methods' => $current['methods'],
                'route' => $current['route'],
                'params' => $params
            ];
        }
    }

    public function addRoute($methods, $route, $handler)
    {
        if (is_string($methods)) {
            $methods = [$methods];
        }

        $parts = explode('?', $route, 1);
        $parts = explode('/', preg_replace(static::SEPARATOR_REGEXP, '', $parts[0]));
        $length = sizeof($parts);

        if ($length === 1 && $parts[0] === '') {
            $parts = [];
            $length = 0;
        }

        $current = &$this->routes;
        for ($i = 0; $i < $length; $i++) {
            $paramsMatch = preg_match(static::PARAM_REGEXP, $parts[$i], $paramsMatches);
            if ($paramsMatch) {
                if (!empty($paramsMatches[2])) {
                    if (!isset($current['regexps'][$paramsMatches[4]])) {
                        $current['regexps'][$paramsMatches[4]] = ['childs' => [], 'regexps' => [], 'name' => $paramsMatches[3]];
                    }
                    $current = &$current['regexps'][$paramsMatches[4]];
                } else {
                    if (!isset($current['others'])) {
                        $current['others'] = ['childs' => [], 'regexps' => [], 'name' => $paramsMatches[5]];
                    }
                    $current = &$current['others'];
                }
            } else {
                if (!isset($current['childs'][$parts[$i]])) {
                    $current['childs'][$parts[$i]] = ['childs' => [], 'regexps' => []];
                }
                $current = &$current['childs'][$parts[$i]];
            }
        }

        $current['route'] = $route;
        for ($i = 0, $length = sizeof($methods); $i < $length; $i++) {
            if (!isset($current['methods'])) {
                $current['methods'] = [];
            }
            $current['methods'][strtoupper($methods[$i])] = $handler;
        }
    }

    public function getOptions($url)
    {
        $route = $this->match($url);
        if (!$route) {
            return null;
        } else {
            return array_keys($route['methods']);
        }
    }

    public function dispatch($method, $url)
    {
        $route = $this->match($url);
        if (!$route) {
            return [
                'error' => [
                    'code' => 404,
                    'message' => 'Not Found'
                ],
                'method' => $method,
                'url' => $url
            ];
        } else {
            if (isset($route['methods'][$method])) {
                return [
                    'method' => $method,
                    'url' => $url,
                    'route' => $route['route'],
                    'params' => $route['params'],
                    'handler' => $route['methods'][$method]
                ];
            } else {
                return [
                    'error' => [
                        'code' => 405,
                        'message' => 'Method Not Allowed'
                    ],
                    'method' => $method,
                    'url' => $url,
                    'route' => $route['route'],
                    'params' => $route['params'],
                    'allowed' => array_keys($route['methods'])
                ];
            }
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    public function options($route, $handler)
    {
        $this->addRoute('OPTIONS', $route, $handler);
    }

    public function get($route, $handler)
    {
        $this->addRoute('GET', $route, $handler);
    }

    public function head($route, $handler)
    {
        $this->addRoute('HEAD', $route, $handler);
    }

    public function post($route, $handler)
    {
        $this->addRoute('POST', $route, $handler);
    }

    public function put($route, $handler)
    {
        $this->addRoute('PUT', $route, $handler);
    }

    public function delete($route, $handler)
    {
        $this->addRoute('DELETE', $route, $handler);
    }

    public function trace($route, $handler)
    {
        $this->addRoute('TRACE', $route, $handler);
    }

    public function connect($route, $handler)
    {
        $this->addRoute('CONNECT', $route, $handler);
    }
}