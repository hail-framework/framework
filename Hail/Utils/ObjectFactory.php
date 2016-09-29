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
	private $namespace;
	private $object;

	public function __construct($name)
	{
		$this->namespace = 'App\\' . $name . '\\';
	}

	public function __get($name)
	{
		return $this->offsetGet($name);
	}

	public function __call($name, $arguments)
	{
		return $this->offsetGet($name);
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetExists($name)
	{
		if (!isset($this->object[$name])) {
			return class_exists($this->namespace . ucfirst($name));
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetGet($name)
	{
		if (!isset($this->object[$name])) {
			$class = $this->namespace . ucfirst($name);
			if (!class_exists($class)) {
				throw new \RuntimeException("Model $name Not Defined");
			}

			return $this->object[$name] = new $class();
		}

		return $this->object[$name];
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetSet($name, $value)
	{
		if (isset($this->object[$name])) {
			return $this->object[$name];
		}

		$class = $this->namespace . ucfirst($name);
		if (!$value instanceof $class) {
			throw new \RuntimeException("Object Not Instance of $class");
		}

		$this->object[$name] = $value;
		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetUnset($name)
	{
		if (isset($this->object[$name])) {
			unset($this->object[$name]);
		}
	}
}