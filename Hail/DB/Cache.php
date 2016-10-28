<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/19 0019
 * Time: 14:01
 */

namespace Hail\DB;

use Hail\DITrait;
use Hail\Utils\Serialize;

/**
 * Class Cache
 *
 * @package Hail\DB
 * @method select(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 * @method get(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 */
class Cache
{
	use DITrait;

	private $lifetime = 0;
	private $name = '';

	public function expiresAfter($lifetime = 0)
	{
		$this->lifetime = $lifetime;
		return $this;
	}

	public function name($name)
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 * @return array|bool|mixed
	 * @throws \InvalidArgumentException
	 */
	public function __call($name, $arguments)
	{
		$key = $this->key($name, $arguments);

		$result = $this->cache->fetch($key);
		if (!$result) {
			switch ($name) {
				case 'get':
					$result = $this->db->get(...$arguments);
				break;

				case 'select':
					$result = $this->db->select(...$arguments);
				break;

				default:
					throw new \InvalidArgumentException('Cache only support select/get method');
			}

			$this->cache->save($key, $result, $this->lifetime);
		}

		$this->reset();
		return $result;
	}

	/**
	 * @param $name
	 * @param array|null $arguments
	 *
	 * @return string
	 */
	protected function key($name, $arguments = null)
	{
		if ($this->name) {
			return $this->name;
		} else if ($arguments === null) {
			return $name;
		} else if (is_string($arguments[0])) {
			return $arguments[0];
		}

		return hash('sha1', Serialize::encode([$name, $arguments]));
	}

	public function reset()
	{
		if ($this->lifetime !== 0) {
			$this->lifetime = 0;
		}

		if ($this->name !== '') {
			$this->name = '';
		}

		return $this;
	}

	public function delete($name, $arguments = null)
	{
		$key = $this->key($name, $arguments);
		$this->reset();

		return $this->cache->delete($key);
	}
}