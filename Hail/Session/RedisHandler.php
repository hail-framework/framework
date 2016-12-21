<?php
namespace Hail\Session;

use Hail\Facades\Config;
use Hail\Redis\{
	Exception\RedisException,
	Factory as RedisFactory
};

/**
 * Class RedisHandler
 *
 * @package Hail\Session
 */
class RedisHandler extends BaseHandler
{
	/**
	 * @var \Hail\Redis\Driver
	 */
	protected $redis;

	/**
	 * RedisHandler constructor.
	 *
	 * @param array $settings
	 * @throws RedisException
	 */
	public function __construct(array $settings)
	{
		$settings += [
			'prefix' => 'sessions',
		];

		if (!isset($settings['lifetime']) || $settings['lifetime'] === 0) {
			$settings['lifetime'] = (int) ini_get('session.gc_maxlifetime');
		}

		$this->redis = RedisFactory::client(
			$settings + Config::get('redis')
		);

		parent::__construct($settings);
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
		$result = $this->redis->del(
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
		return $this->redis->isConnected();
	}

	/**
	 * {@inheritDoc}
	 */
	public function read($id)
	{
		return $this->redis->get(
			$this->key($id)
		) ?: '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($id, $data)
	{
		return $this->redis->setEx(
			$this->key($id), $this->settings['lifetime'], $data
		);
	}
}