<?php
namespace Hail\DB;

use InvalidArgumentException;
use Hail\Facades\Serialize;
use Hail\Facades\{
	DB,
	Cache as SimpleCache
};

/**
 * Class Cache
 *
 * @package Hail\DB
 * @author Hao Feng <flyinghail@msn.com>
 *
 * @method select(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 * @method get(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 */
class Cache
{
	private $lifetime = 0;
	private $name = '';

	/**
	 * @param int $lifetime
	 *
	 * @return $this
	 */
	public function expiresAfter($lifetime = 0)
	{
		$this->lifetime = $lifetime;
		return $this;
	}

	/**
	 * @param string $name
	 *
	 * @return $this
	 */
	public function name(string $name)
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 *
	 * @return array|bool|mixed
	 * @throws InvalidArgumentException
	 */
	public function __call($name, $arguments)
	{
		$key = $this->key($name, $arguments);

		$result = SimpleCache::fetch($key);
		if (!$result) {
			if ($name === 'get' || $name === 'select') {
				switch (count($arguments)) {
					case 1:
						$result = DB::$name($arguments[0]);
						break;

					case 2:
						$result = DB::$name($arguments[0], $arguments[1]);
						break;

					case 3:
						$result = DB::$name($arguments[0], $arguments[1], $arguments[2]);
						break;

					default:
						throw new InvalidArgumentException('Aruguments number out of range');
						break;
				}
			} else {
				throw new InvalidArgumentException('Cache only support select/get method');
			}

			SimpleCache::save($key, $result, $this->lifetime);
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

		return sha1(Serialize::encode([$name, $arguments]));
	}

	/**
	 * @return $this
	 */
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

	/**
	 * @param string $name
	 * @param mixed $arguments
	 *
	 * @return bool
	 */
	public function delete(string $name, $arguments = null)
	{
		$key = $this->key($name, $arguments);
		$this->reset();

		return SimpleCache::delete($key);
	}
}