<?php
namespace Hail\Cache\Simple;

/**
 * Array cache driver.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class ArrayData extends AbstractAdapter
{
	/**
	 * @var array $data
	 */
	private $data = [];

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		if (isset($this->data[$key])) {
			$value = $this->data[$key];
			if ($value['expire'] >= \time()) {
				return $value['value'];
			}

			unset($this->data[$key]);
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		return isset($this->data[$key]) && $this->data[$key]['expire'] >= \time();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		$this->data[$key] = [
			'value' => $value,
			'expire' => \time() + $ttl
		];

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		unset($this->data[$key]);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		$this->data = [];

		return true;
	}
}