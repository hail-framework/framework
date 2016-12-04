<?php
/**
 * @from https://github.com/baryshev/TreeRoute
 * Copyright (c) 2015, Vadim Baryshev
 */

namespace Hail;

use Hail\Utils\OptimizeTrait;
use Hail\Facades\Serialize;

/**
 * Class Router
 *
 * @package Hail
 * @author Hao Feng <flyinghail@msn.com>
 */
class Router
{
	use OptimizeTrait;

	const PARAM_REGEXP = '/^{((([^:]+):(.+))|(.+))}$/';
	const SEPARATOR_TRIM = "/ \t\n\r";
	private $routes = ['childs' => [], 'regexps' => []];
	private $result = [];

	public function __construct($config)
	{
		$this->addRoutes($config);
	}

	private function match($url)
	{
		$parts = explode('?', $url, 2);
		$parts = explode('/', trim($parts[0], static::SEPARATOR_TRIM));
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

	/**
	 * @param array $methods
	 * @param string $route
	 * @param array $handler
	 */
	public function addRoute(array $methods, string $route, array $handler)
	{
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

	public function options(string $route, array $handler)
	{
		$this->addRoute(['OPTIONS'], $route, $handler);
	}

	public function get(string $route, array $handler)
	{
		$this->addRoute(['GET'], $route, $handler);
	}

	public function head(string $route, array $handler)
	{
		$this->addRoute(['HEAD'], $route, $handler);
	}

	public function post(string $route, array $handler)
	{
		$this->addRoute(['POST'], $route, $handler);
	}

	public function put(string $route, array $handler)
	{
		$this->addRoute(['PUT'], $route, $handler);
	}

	public function delete(string $route, array $handler)
	{
		$this->addRoute(['DELETE'], $route, $handler);
	}

	public function trace(string $route, array $handler)
	{
		$this->addRoute(['TRACE'], $route, $handler);
	}

	public function connect(string $route, array $handler)
	{
		$this->addRoute(['CONNECT'], $route, $handler);
	}
}