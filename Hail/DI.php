<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/14 0019
 * Time: 15:30
 */

namespace Hail;

use Pimple\Container;

if (!extension_loaded('pimple')) {
	require __DIR__ . '/Pimple/Container.php';
}

/**
 * Class DI
 * @package Hail
 */
class DI extends Container
{
	public function set($id, $value)
	{
		$this->offsetSet($id, $value);
	}

	public function get($id)
	{
		return $this->offsetGet(
			strtolower($id)
		);
	}

	public function has($id)
	{
		return $this->offsetExists($id);
	}

	public function __call($func, $args)
	{
		if (isset($args[0]) && $args[0] instanceof \Closure) {
			$this->extend($func, $args[0]);
		}
		return $this->get($func);
	}

	public function __get($id)
	{
		return $this->get($id);
	}
}