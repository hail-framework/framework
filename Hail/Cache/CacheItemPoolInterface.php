<?php
namespace Hail\Cache;

use Psr\Cache\CacheItemPoolInterface as PsrCacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Interface CacheItemPoolInterface
 *
 * @package Hail\Cache
 * @author Hao Feng <flyinghail@msn.com>
 */
interface CacheItemPoolInterface extends PsrCacheItemPoolInterface
{
	/**
	 * Invalidates cached items using a tag.
	 *
	 * @param string $tag The tag to invalidate
	 *
	 * @throws InvalidArgumentException When $tags is not valid
	 *
	 * @return bool True on success
	 */
	public function invalidateTag($tag);
	/**
	 * Invalidates cached items using tags.
	 *
	 * @param string[] $tags An array of tags to invalidate
	 *
	 * @throws InvalidArgumentException When $tags is not valid
	 *
	 * @return bool True on success
	 */
	public function invalidateTags(array $tags);

	/**
	 * {@inheritdoc}
	 *
	 * @return CacheItemInterface
	 */
	public function getItem($key);

	/**
	 * {@inheritdoc}
	 *
	 * @return array|\Traversable|CacheItemInterface[]
	 */
	public function getItems(array $keys = []);
}
