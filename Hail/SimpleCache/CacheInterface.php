<?php

namespace Hail\SimpleCache;

/**
 * Interface CacheInterface
 *
 * @package Hail\SimpleCache
 * @author Hao Feng <flyinghail@msn.com>
 */
interface CacheInterface extends \Psr\SimpleCache\CacheInterface, \ArrayAccess
{
	/**
	 * Deletes all cache entries in the current cache namespace.
	 *
	 * @return bool TRUE if the cache entries were successfully deleted, FALSE otherwise.
	 */
	public function deleteAll();

	/**
	 * Sets the namespace to prefix all cache ids with.
	 *
	 * @param string $namespace
	 *
	 * @return void
	 */
	public function setNamespace(string $namespace);

	/**
	 * Retrieves the namespace that prefixes all cache ids.
	 *
	 * @return string
	 */
	public function getNamespace();

}