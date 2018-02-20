<?php
namespace Hail\Session\Handler;


/**
 * Class RedisHandler
 *
 * @package Hail\Session
 */
class Redis extends AbstractHandler
{
	/**
	 * @var \Hail\Redis\Client\AbstractClient
	 */
	protected $redis;

	/**
	 * RedisHandler constructor.
	 *
	 * @param $redis
	 * @param array $settings
	 */
	public function __construct($redis, array $settings)
	{
		$settings += [
			'prefix' => 'redisses_',
		];

		$this->redis = $redis;

		parent::__construct($settings);
	}

    /**
     * {@inheritdoc}
     */
    public function updateTimestamp($sessionId, $data)
    {
        return $this->redis->expire($this->settings['prefix'] . $sessionId, $this->settings['lifetime']);
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
	protected function doDestroy($sessionId)
	{
		$result = $this->redis->del($this->settings['prefix'] . $sessionId);

		return $result !== false;
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
    protected function doRead($sessionId)
	{
		return $this->redis->get(
            $this->settings['prefix'] . $sessionId
		) ?: '';
	}

	/**
	 * {@inheritDoc}
	 */
    protected function doWrite($sessionId, $data)
	{
		return $this->redis->setEx(
            $this->settings['prefix'] . $sessionId, $this->settings['lifetime'], $data
		);
	}
}