<?php
namespace Hail;

use Hail\Container\Container;
use Hail\Http\Emitter\Sapi;

class Application
{
	/**
	 * @var Container
	 */
	private $container;

	/**
	 * Application constructor.
	 *
	 * @param Container $container
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
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

	public function run()
	{
		$response = $this->get('http.dispatcher')->dispatch(
			$this->get('http.request')
		);

		(new Sapi())->emit($response);
	}

	/**
	 * @param string      $root
	 * @param string|null $path
	 *
	 * @return string
	 */
	public static function path(string $root, string $path = null): string
	{
		if ($path === null || $path === '') {
			return $root;
		}

		if (strpos($path, '..') !== false) {
			throw new \InvalidArgumentException('Unable to get a directory higher than ROOT');
		}

		$path = str_replace('\\', '/', $path);
		if ($path[0] === '/') {
			$path = ltrim($path, '/');
		}

		return realpath($root . $path) ?: $root . $path;
	}
}