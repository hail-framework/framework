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

use Hail\Facades\Serialize;
use MongoDB\Collection;
use MongoDB\Driver\Manager;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Magnus Nordlander
 */
class MongoDBCachePool extends AbstractCachePool
{
	/**
	 * @type Collection
	 */
	private $collection;

	/**
	 * @param Collection $collection
	 */
	public function __construct(Collection $collection)
	{
		$this->collection = $collection;
	}

	/**
	 * @param Manager $manager
	 * @param string  $database
	 * @param string  $collection
	 *
	 * @return Collection
	 */
	public static function createCollection(Manager $manager, $database, $collection)
	{
		$collection = new Collection($manager, $database, $collection);
		$collection->createIndex(['expireAt' => 1], ['expireAfterSeconds' => 0]);

		return $collection;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function fetchObjectFromCache($key)
	{
		$object = $this->collection->findOne(['_id' => $key]);

		if (!$object || !isset($object->data)) {
			return [false, null, [], null];
		}

		if (isset($object->expiresAt)) {
			if ($object->expiresAt < time()) {
				return [false, null, [], null];
			}
		}

		return [true, Serialize::decode($object->data), $object->tags, $object->expirationTimestamp];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function clearAllObjectsFromCache()
	{
		$this->collection->deleteMany([]);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function clearOneObjectFromCache($key)
	{
		$this->collection->deleteOne(['_id' => $key]);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function storeItemInCache(PhpCacheItem $item, $ttl)
	{
		$object = [
			'_id' => $item->getKey(),
			'data' => Serialize::encode($item->get()),
			'tags' => [],
			'expirationTimestamp' => $item->getExpirationTimestamp(),
		];

		if ($ttl) {
			$object['expiresAt'] = time() + $ttl;
		}

		$this->collection->updateOne(['_id' => $item->getKey()], ['$set' => $object], ['upsert' => true]);

		return true;
	}
}
