<?php
namespace Hail\SimpleCache;

/**
 * YAC cache provider.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class Yac extends AbtractAdapter
{
	/**
	 * @var \Yac
	 */
	private $yac;

	public function __construct($params)
	{
		$this->yac = new \Yac();
		parent::__construct($params);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		$value = $this->yac->get(
			$this->key($key)
		);
		return $value === false ? null : $value;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		return $this->yac->get($this->key($key)) !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		return $this->yac->set($this->key($key), $value, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		return $this->yac->delete(
			$this->key($key)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		return $this->yac->flush();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		$list = [];
		foreach ($values as $k => $v) {
			$list[$this->key($k)] = $v;
		}

		return $this->yac->set($list, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetMultiple(array $keys)
	{
		return $this->yac->get(array_map([$this, 'key'], $keys));
	}

	public function key($key)
	{
		if (strlen($key) > \YAC_MAX_KEY_LEN) {
			return sha1($key);
		}

		return $key;
	}
}
