<?php
namespace Hail\SimpleCache;

/**
 * Array cache driver.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class ArrayData extends AbtractAdapter
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
			if ($value['expire'] >= NOW) {
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
		return isset($this->data[$key]) && $this->data[$key]['expire'] >= NOW;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		$this->data[$key] = [
			'value' => $value,
			'expire' => NOW + $ttl
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