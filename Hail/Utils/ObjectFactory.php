<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 15:07
 */

namespace Hail\Utils;

/**
 * Class Model
 * @package Hail
 * @property-read \App\Library\Menu $menu
 * @property-read \App\Library\Data $data
 * @property-read \App\Library\Enum $enum
 * @property-read \App\Library\OperationLog $OperationLog
 * @property-read \App\Library\GameApi $gameApi
 * @property-read \App\Library\GameTime $gameTime
 * @property-read \App\Library\Organization $organization
 * @property-read \App\Library\Acl $acl
 * @property-read \App\Library\Language $language
 * @property-read \App\Library\Game $game
 * @property-read \App\Library\Server $server
 * @property-read \App\Library\Channel $channel
 * @property-read \App\Library\Statistics $statistics
 * @property-read \App\Library\Excel $excel
 */
class ObjectFactory implements \ArrayAccess
{
	use ArrayTrait;

	private $namespace;
	private $object;

	public function __construct($namespace)
	{
		$this->namespace = trim($namespace, '\\') . '\\';
	}

	public function __call($name, $arguments)
	{
		return $this->get($name);
	}

	public function has($key)
	{
		if (!isset($this->object[$key])) {
			return class_exists($this->namespace . ucfirst($key));
		}

		return true;
	}

	public function get($key)
	{
		if (!isset($this->object[$key])) {
			$class = $this->namespace . ucfirst($key);
			if (!class_exists($class)) {
				throw new \RuntimeException("Class $class Not Defined");
			}

			return $this->object[$key] = new $class();
		}

		return $this->object[$key];
	}

	public function set($key, $value)
	{
		if (isset($this->object[$key])) {
			return $this->object[$key];
		}

		$class = $this->namespace . ucfirst($key);
		if (!$value instanceof $class) {
			throw new \RuntimeException("Object Not Instance of $class");
		}

		$this->object[$key] = $value;
		return $value;
	}

	public function delete($key)
	{
		if (isset($this->object[$key])) {
			unset($this->object[$key]);
		}
	}
}