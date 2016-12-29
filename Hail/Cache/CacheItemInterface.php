<?php
namespace Hail\Cache;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface CacheItemInterface extends TaggableItemInterface
{
	/**
	 * The timestamp when the object expires.
	 *
	 * @return int|null
	 */
	public function getExpirationTimestamp();
}
