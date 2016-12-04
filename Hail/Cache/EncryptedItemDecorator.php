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

use Hail\Cache\Adapter\HasExpirationDateInterface;
use Hail\Facades\Crypto;
use Hail\Facades\Serialize;
use Psr\Cache\CacheItemInterface;

/**
 * Encrypt and Decrypt all the stored items.
 *
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class EncryptedItemDecorator implements CacheItemInterface, HasExpirationDateInterface, TaggableItemInterface
{
	/**
	 * @type CacheItemInterface
	 */
	private $cacheItem;

	/**
	 * @type string
	 */
	private $key;

	/**
	 * @param CacheItemInterface $cacheItem
	 * @param string $key
	 */
	public function __construct(CacheItemInterface $cacheItem, string $key)
	{
		$this->cacheItem = $cacheItem;
		$this->key = $key;
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
		$serialized = Serialize::encode($value);
		$this->cacheItem->set(
			Crypto::encrypt($serialized, $this->key)
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
			Crypto::decrypt($this->cacheItem->get(), $this->key)
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
	public function getExpirationDate()
	{
		return $this->cacheItem->getExpirationDate();
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
