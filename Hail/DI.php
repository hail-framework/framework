<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/14 0019
 * Time: 15:30
 */

namespace Hail;

if (!extension_loaded('pimple')) {
	require __DIR__ . '/Pimple/Container.php';
}

class DI extends \Pimple\Container
{
	public function set($id, $value)
	{
		$this->offsetSet($id, $value);
	}

	public function __call($func, $args)
	{
		if (isset($args[0]) && $args[0] instanceof \Closure) {
			$this->extend($func, $args[0]);
		}
		return $this->offsetGet($func);
	}

	public function __get($id)
	{
		return $this->offsetGet($id);
	}
}