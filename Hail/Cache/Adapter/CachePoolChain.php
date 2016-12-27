<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Hail\Cache\Adapter;

use Hail\Cache\Exception\NoPoolAvailableException;
use Hail\Cache\Exception\PoolFailedException;
use Hail\Cache\Exception\CachePoolException;
use Hail\Cache\TaggablePoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachePoolChain implements CacheItemPoolInterface, TaggablePoolInterface
{
	/**
	 * @type CacheItemPoolInterface[]
	 */
	private $pools;

	/**
	 * @type array
	 */
	private $options;

	/**
	 * @param array $pools
	 * @param array $options [@type bool   $skip_on_failure If true we will remove a pool form the chain if it fails.]
	 * @throws NoPoolAvailableException
	 */
	public function __construct(array $pools, array $options = [])
	{
		if ($pools === []) {
			throw new NoPoolAvailableException('No valid cache pool available for the chain.');
		}

		$this->pools = $pools;

		if (!isset($options['skip_on_failure'])) {
			$options['skip_on_failure'] = false;
		}

		$this->options = $options;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getItem($key)
	{
		$found = false;
		$result = null;
		$needsSave = [];

		foreach ($this->pools as $poolKey => $pool) {
			try {
				$item = $pool->getItem($key);

				if ($item->isHit()) {
					$found = true;
					$result = $item;
					break;
				}

				$needsSave[] = $pool;
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		if ($found) {
			foreach ($needsSave as $pool) {
				$pool->save($result);
			}

			$item = $result;
		}

		return $item;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getItems(array $keys = [])
	{
		$hits = [];
		$items = [];
		foreach ($this->pools as $poolKey => $pool) {
			try {
				$items = $pool->getItems($keys);

				/** @type CacheItemInterface $item */
				foreach ($items as $item) {
					if ($item->isHit()) {
						$hits[$item->getKey()] = $item;
					}
				}

				if (count($hits) === count($keys)) {
					return $hits;
				}
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		// We need to accept that some items where not hits.
		return array_merge($hits, $items);
	}

	/**
	 * {@inheritdoc}
	 */
	public function hasItem($key)
	{
		foreach ($this->pools as $poolKey => $pool) {
			try {
				if ($pool->hasItem($key)) {
					return true;
				}
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear()
	{
		$result = true;
		foreach ($this->pools as $poolKey => $pool) {
			try {
				$result = $result && $pool->clear();
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteItem($key)
	{
		$result = true;
		foreach ($this->pools as $poolKey => $pool) {
			try {
				$result = $result && $pool->deleteItem($key);
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteItems(array $keys)
	{
		$result = true;
		foreach ($this->pools as $poolKey => $pool) {
			try {
				$result = $result && $pool->deleteItems($keys);
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function save(CacheItemInterface $item)
	{
		$result = true;
		foreach ($this->pools as $poolKey => $pool) {
			try {
				$result = $pool->save($item) && $result;
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function saveDeferred(CacheItemInterface $item)
	{
		$result = true;
		foreach ($this->pools as $poolKey => $pool) {
			try {
				$result = $pool->saveDeferred($item) && $result;
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function commit()
	{
		$result = true;
		foreach ($this->pools as $poolKey => $pool) {
			try {
				$result = $pool->commit() && $result;
			} catch (CachePoolException $e) {
				$this->handleException($poolKey, $e);
			}
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clearTags(array $tags)
	{
		$result = true;
		foreach ($this->pools as $poolKey => $pool) {
			if ($pool instanceof TaggablePoolInterface) {
				try {
					$result = $pool->clearTags($tags) && $result;
				} catch (CachePoolException $e) {
					$this->handleException($poolKey, $e);
				}
			}
		}

		return $result;
	}

	/**
	 * @return array|\Psr\Cache\CacheItemPoolInterface[]
	 */
	protected function getPools()
	{
		return $this->pools;
	}

	/**
	 * @param string             $poolKey
	 * @param CachePoolException $exception
	 *
	 * @throws PoolFailedException
	 */
	private function handleException($poolKey, CachePoolException $exception)
	{
		if (!$this->options['skip_on_failure']) {
			throw $exception;
		}

		unset($this->pools[$poolKey]);
	}
}
