<?php
/**
 * @from https://github.com/baryshev/TreeRoute
 * Copyright (c) 2015, Vadim Baryshev Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail;

use Hail\Utils\OptimizeTrait;
use Hail\Utils\Serialize;

class Router
{
	use OptimizeTrait;

	const PARAM_REGEXP = '/^{((([^:]+):(.+))|(.+))}$/';
	const SEPARATOR_REGEXP = '/^[\s\/]+|[\s\/]+$/';
	private $routes = ['childs' => [], 'regexps' => []];
	private $result = [];

	public function __construct($config)
	{
		$this->addRoutes($config);
	}

	private function match($url)
	{
		$parts = explode('?', $url, 2);
		$parts = explode('/', preg_replace(static::SEPARATOR_REGEXP, '', $parts[0]));
		if (!isset($parts[1]) && $parts[0] === '') {
			$parts = [];
		}

		$params = [];
		$current = $this->routes;
		foreach ($parts as $v) {
			if (isset($current['childs'][$v])) {
				$current = $current['childs'][$v];
			} else {
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
		$sign = hash('sha1', Serialize::encode($config));
		$check = $this->optimizeGet('routes_sign');
		if ($check === $sign) {
			$this->routes = $this->optimizeGet('routes');

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

		$this->optimizeSet([
			'routes' => $this->routes,
			'routes_sign' => $sign,
		]);
	}

	public function addRoute($methods, $route, $handler)
	{
		$methods = (array) $methods;

		$parts = explode('/', preg_replace(static::SEPARATOR_REGEXP, '', $route));

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
							'childs' => [],
							'regexps' => [],
							'name' => $paramsMatches[3],
						];
					}
					$current = &$current['regexps'][$paramsMatches[4]];
				} else {
					if (!isset($current['others'])) {
						$current['others'] = [
							'childs' => [],
							'regexps' => [],
							'name' => $paramsMatches[5],
						];
					}
					$current = &$current['others'];
				}
			} else {
				if (!isset($current['childs'][$v])) {
					$current['childs'][$v] = [
						'childs' => [],
						'regexps' => [],
					];
				}
				$current = &$current['childs'][$v];
			}
		}

		$current['route'] = $route;
		foreach ($methods as $v) {
			if (!isset($current['methods'])) {
				$current['methods'] = [];
			}
			$current['methods'][strtoupper($v)] = $handler;
		}
	}

	public function getOptions($url)
	{
		$route = $this->match($url);
		if (!$route) {
			return null;
		}

		return array_keys($route['methods']);
	}

	public function dispatch($method, $url)
	{
		$route = $this->match($url);
		if (!$route) {
			return $this->result = [
				'error' => 404,
				'method' => $method,
				'url' => $url,
			];
		} else if (isset($route['methods'][$method])) {
			$params = $route['params'];
			$handler = $route['methods'][$method];

			return $this->result = [
				'method' => $method,
				'url' => $url,
				'route' => $route['route'],
				'params' => $params,
				'handler' => [
					'app' => $handler['app'],
					'controller' => $handler['controller'] ?? $params['controller'] ?? '',
					'action' => $handler['action'] ?? $params['action'] ?? '',
				],
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