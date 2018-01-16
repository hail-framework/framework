<?php
namespace Hail\Cache;

use Psr\Cache\CacheItemInterface as PsrCacheItemInterface;
use Psr\Cache\InvalidArgumentException;


/**
 * Interface CacheItemInterface
 *
 * @package Hail\Cache
 * @author Feng Hao <flyinghail@msn.com>
 */
interface CacheItemInterface extends PsrCacheItemInterface
{
	/**
	 * The timestamp when the object expires.
	 *
	 * @return int|null
	 */
	public function getExpirationTimestamp();

	/**
	 * Get all existing tags. These are the tags the item has when the item is
	 * returned from the pool.
	 *
	 * @return array
	 */
	public function getPreviousTags();

	/**
	 * Overwrite all tags with a new set of tags.
	 *
	 * @param string[] $tags An array of tags
	 *
	 * @throws InvalidArgumentException When a tag is not valid.
	 *
	 * @return CacheItemInterface
	 */
	public function setTags(array $tags);

	/**
     * Get the current tags. These are not the same tags as getPrevious tags.
     *
     * @return array
     */
    public function getTags();
}
