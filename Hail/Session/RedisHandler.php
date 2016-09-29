<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/15 0015
 * Time: 18:06
 */

namespace Hail\Session;
use Hail\Cache\Driver\Redis;

/**
 * Class RedisHandler
 *
 * @package Hail\Session
 */
class RedisHandler extends BaseHandler
{
	protected $redis;
	protected $settings = [
		'prefix' => 'sessions'
	];

	public function __construct(array $settings)
	{
		if (!isset($settings['lifetime']) || $settings['lifetime'] === 0) {
			$settings['lifeTime'] = ini_get('session.gc_maxlifetime');
		}
		$settings['namespace'] = 'sessions';

		parent::__construct($settings);
		$this->redis = new Redis($this->settings);
	}

	protected function key($id)
	{
		return $this->settings['prefix'] . '_' . $id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function close()
	{
		$this->redis = null;
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function destroy($id)
	{
		$result = $this->redis->delete(
			$this->key($id)
		);
		return $result !== false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function gc($lifetime)
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function open($path, $name)
	{
		return $this->redis ? true : false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read($id)
	{
		return $this->redis->fetch(
			$this->key($id)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($id, $data)
	{
		return $this->redis->save(
			$this->key($id), $data
		);
	}
}