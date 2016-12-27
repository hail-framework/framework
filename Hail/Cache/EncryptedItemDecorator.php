<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Hail\Cache;

use Hail\Cache\Adapter\PhpCacheItem;
use Hail\Facades\Crypto;
use Hail\Facades\Serialize;
use Hail\Util\SafeStorage;
use Psr\Cache\CacheItemInterface;

/**
 * Encrypt and Decrypt all the stored items.
 *
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class EncryptedItemDecorator implements PhpCacheItem, TaggableItemInterface
{
	/**
	 * @type CacheItemInterface
	 */
	private $cacheItem;

	/**
	 * @var SafeStorage
	 */
	private $safeStorage;

	/**
	 * @param CacheItemInterface $cacheItem
	 * @param string $key
	 */
	public function __construct(CacheItemInterface $cacheItem, string $key)
	{
		$this->cacheItem = $cacheItem;
		$this->safeStorage = new SafeStorage();
		$this->safeStorage->set('key', $key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getKey()
	{
		return $this->cacheItem->getKey();
	}

	/**
	 * {@inheritdoc}
	 */
	public function set($value)
	{
		$this->cacheItem->set(
			Crypto::encrypt(
				Serialize::encode($value),
				$this->safeStorage->get('key')
			)
		);

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get()
	{
		if (!$this->isHit()) {
			return null;
		}

		return Serialize::decode(
			Crypto::decrypt(
				$this->cacheItem->get(),
				$this->safeStorage->get('key')
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isHit()
	{
		return $this->cacheItem->isHit();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getExpirationTimestamp()
	{
		return $this->cacheItem->getExpirationTimestamp();
	}

	/**
	 * {@inheritdoc}
	 */
	public function expiresAt($expiration)
	{
		$this->cacheItem->expiresAt($expiration);

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function expiresAfter($time)
	{
		$this->cacheItem->expiresAfter($time);

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTags()
	{
		return $this->cacheItem->getTags();
	}

	/**
	 * {@inheritdoc}
	 */
	public function addTag($tag)
	{
		$this->cacheItem->addTag($tag);

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setTags(array $tags)
	{
		$this->cacheItem->setTags($tags);

		return $this;
	}

	/**
	 * Creating a copy of the orginal CacheItemInterface object.
	 */
	public function __clone()
	{
		$this->cacheItem = clone $this->cacheItem;
	}
}
