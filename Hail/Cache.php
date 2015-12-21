<?php

namespace Hail;

use Hail\Cache\Driver;

/**
 * Class Cache
 * @package Hail
 */
class Cache extends Driver
{
	/**
	 * @var Driver[]
	 */
	private $drivers = [];

	/**
	 *
	 * @param array $params
	 */
	public function __construct($params)
	{
		if (!isset($params['drivers'])) {
			$params['drivers'] = ['void' => []];
		}

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

			$class = 'Cache\\Driver\\' . $k;
			$this->drivers[] = new $class($v);
		}

		if (isset($params['lifetime'])) {
			unset($params['lifetime']);
		}

		parent::__construct($params);
	}

	/**
	 * {@inheritDoc}
	 */
	public function setNamespace($namespace)
	{
		parent::setNamespace($namespace);

		foreach ($this->drivers as $driver) {
			$driver->setNamespace($namespace);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doFetch($id)
	{
		foreach ($this->drivers as $key => $driver) {
			if ($driver->doContains($id)) {
				$value = $driver->doFetch($id);

				// We populate all the previous cache layers (that are assumed to be faster)
				for ($subKey = $key - 1; $subKey >= 0; $subKey--) {
					$this->drivers[$subKey]->doSave($id, $value);
				}

				return $value;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doContains($id)
	{
		foreach ($this->drivers as $driver) {
			if ($driver->doContains($id)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doSave($id, $data, $lifetime = 0)
	{
		$stored = true;

		foreach ($this->drivers as $driver) {
			$lifetime = $driver->getLifetime($lifetime);
			$stored = $driver->doSave($id, $data, $lifetime) && $stored;
		}

		return $stored;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doDelete($id)
	{
		$deleted = true;

		foreach ($this->drivers as $driver) {
			$deleted = $driver->doDelete($id) && $deleted;
		}

		return $deleted;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doFlush()
	{
		$flushed = true;

		foreach ($this->drivers as $driver) {
			$flushed = $driver->doFlush() && $flushed;
		}

		return $flushed;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doGetStats()
	{
		// We return all the stats from all adapters
		$stats = array();

		foreach ($this->drivers as $driver) {
			$stats[] = $driver->doGetStats();
		}

		return $stats;
	}
}
