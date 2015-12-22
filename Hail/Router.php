<?php
/**
 * @from https://github.com/baryshev/TreeRoute
 * Copyright (c) 2015, Vadim Baryshev Modifiend by FlyingHail <flyinghail@msn.com>
 */

namespace Hail;

use Hail\Cache\EmbeddedTrait;

class Router
{
	use EmbeddedTrait;

	const PARAM_REGEXP = '/^{((([^:]+):(.+))|(.+))}$/';
	const SEPARATOR_REGEXP = '/^[\s\/]+|[\s\/]+$/';
	private $routes = ['childs' => [], 'regexps' => []];
	private $result = [];

	public function __construct($di)
	{
		$this->initCache($di);
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
			'params' => $params
		];
	}

	/**
	 * @param array $config
	 */
	public function addRoutes($config)
	{
		$sign = hash('md4', json_encode($config));
		$check = $this->getCache('routes_sign');
		if (is_string($check) && $check === $sign) {
			$this->routes = $this->getCache('routes');
			return;
		}

		foreach ($config as $app => $rules) {
			$app = ucfirst($app);
			foreach ($rules as $rule) {
				$handler = ['app' => $app];
				$methods = ['GET', 'POST'];

				if (is_string($rule)) {
					$route = $rule;
				} else {
					$methods = $rule['methods'] ?? $methods;
					if (empty($rule['route'])) {
						throw new \RuntimeException('Router rules error: ' . json_encode($rule));
					}
					$route = $rule['route'];

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
		$this->setCache('routes', $this->routes);
		$this->setCache('routes_sign', $sign);
	}

	private function addRoute($methods, $route, $handler)
	{
		$methods = (array)$methods;

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
							'name' => $paramsMatches[3]
						];
					}
					$current = &$current['regexps'][$paramsMatches[4]];
				} else {
					if (!isset($current['others'])) {
						$current['others'] = [
							'childs' => [],
							'regexps' => [],
							'name' => $paramsMatches[5]
						];
					}
					$current = &$current['others'];
				}
			} else {
				if (!isset($current['childs'][$v])) {
					$current['childs'][$v] = [
						'childs' => [],
						'regexps' => []
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
				'url' => $url
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
				]
			];
		}

		return $this->result = [
			'error' => 405,
			'method' => $method,
			'url' => $url,
			'route' => $route['route'],
			'params' => $route['params'],
			'allowed' => array_keys($route['methods'])
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