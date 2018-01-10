<?php
namespace Hail\Session\Handler;

use Psr\SimpleCache\CacheInterface;

/**
 * Class CacheHandler.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class SimpleCache extends BaseHandler
{
	/**
	 * @var CacheInterface
	 */
	private $cache;

	public function __construct(CacheInterface $cache, array $settings)
	{
		$settings += [
			'prefix' => 'PSR16Ses',
		];

		if (!isset($settings['lifetime']) || $settings['lifetime'] === 0) {
			$settings['lifetime'] = (int) \ini_get('session.gc_maxlifetime');
		}

		$settings['lifetime'] = $settings['lifetime'] ?: 86400;

		$this->cache = $cache;

		parent::__construct($settings);
	}

	/**
	 * {@inheritdoc}
	 */
	public function open($savePath, $sessionName)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function close()
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($id)
	{
		return $this->cache->get($this->key($id), '');
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($id, $data)
	{
		return $this->cache->set($this->key($id), $data, $this->settings['lifetime']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function destroy($id)
	{
		return $this->cache->delete(
			$this->key($id)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function gc($lifetime)
	{
		// not required here because cache will auto expire the records anyhow.
		return true;
	}
}
