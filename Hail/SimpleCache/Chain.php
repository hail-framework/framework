<?php

namespace Hail\SimpleCache;

/**
 * Class Cache
 *
 * @package Hail
 */
class Chain extends AbtractAdapter
{
	/**
	 * @var AbtractAdapter[]
	 */
	private $drivers = [];

	/**
	 *
	 * @param array $params
	 */
	public function __construct($params)
	{
		foreach ($params['drivers'] as $k => $v) {
			switch ($k) {
				case 'array':
				case 'zend':
					$k = ucfirst($k) . 'Data';
					break;
				case 'apc':
					$k = 'Apcu';
					break;
				default:
					$k = ucfirst($k);
			}

			$class = __NAMESPACE__ . '\\' . $k;
			$this->drivers[] = new $class($v);
		}

		parent::__construct($params);
	}

	/**
	 * {@inheritDoc}
	 */
	public function setNamespace(string $namespace)
	{
		parent::setNamespace($namespace);

		foreach ($this->drivers as $driver) {
			$driver->setNamespace($namespace);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doGet(string $key)
	{
		foreach ($this->drivers as $k => $driver) {
			if (($value = $driver->doGet($key)) !== null) {
				// We populate all the previous cache layers (that are assumed to be faster)
				for ($subKey = $k - 1; $subKey >= 0; $subKey--) {
					$this->drivers[$subKey]->doSet($key, $value);
				}

				return $value;
			}
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetMultiple(array $keys)
	{
		$count = count($keys);
		$values = [];

		foreach ($this->drivers as $key => $driver) {
			$values = $driver->doGetMultiple($keys);

			// We populate all the previous cache layers (that are assumed to be faster)
			if (count($values) === $count) {
				for ($subKey = $key - 1; $subKey >= 0; $subKey--) {
					$this->$driver[$subKey]->doSetMultiple($values);
				}

				return $values;
			}
		}

		return $values;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doHas(string $key)
	{
		foreach ($this->drivers as $driver) {
			if ($driver->doHas($key)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doSet(string $key, $data, int $ttl = 0)
	{
		$stored = true;

		foreach ($this->drivers as $driver) {
			$ttl = $driver->ttl($ttl);
			$stored = $driver->doSet($key, $data, $ttl) && $stored;
		}

		return $stored;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		$stored = true;

		foreach ($this->drivers as $driver) {
			$stored = $driver->doSetMultiple($values, $ttl) && $stored;
		}

		return $stored;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doDelete(string $key)
	{
		$deleted = true;

		foreach ($this->drivers as $driver) {
			$deleted = $driver->doDelete($key) && $deleted;
		}

		return $deleted;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDeleteMultiple(array $keys)
	{
		$deleted = true;

		foreach ($this->drivers as $driver) {
			$deleted = $driver->doDeleteMultiple($keys) && $deleted;
		}

		return $deleted;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doClear()
	{
		$flushed = true;

		foreach ($this->drivers as $driver) {
			$flushed = $driver->doClear() && $flushed;
		}

		return $flushed;
	}
}
