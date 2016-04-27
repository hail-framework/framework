<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Hail\Cache;

/**
 * Base class for cache provider implementations.
 *
 * @since  2.2
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 * @author FlyingHail <flyinghail@msn.com>
 */
abstract class Driver
{
	const STATS_HITS = 'hits';
	const STATS_MISSES = 'misses';
	const STATS_UPTIME = 'uptime';
	const STATS_MEMORY_USAGE = 'memory_usage';
	const STATS_MEMORY_AVAILABLE = 'memory_available';

	const CACHEKEY = 'CacheVersion';

	/**
	 * The namespace to prefix all cache ids with.
	 *
	 * @var string
	 */
	private $namespace;

	/**
	 * The namespace version.
	 *
	 * @var integer|null
	 */
	private $namespaceVersion;
	private $lifetime;

	public function __construct($params)
	{
		$lifetime = $params['lifetime'] ?? 0;
		$this->lifetime = (int) $lifetime;

		$this->setNamespace($params['namespace'] ?? '');
	}


	/**
	 * Sets the namespace to prefix all cache ids with.
	 *
	 * @param string $namespace
	 *
	 * @return void
	 */
	public function setNamespace($namespace)
	{
		$this->namespace = (string)$namespace;
		$this->namespaceVersion = null;
	}

	/**
	 * Retrieves the namespace that prefixes all cache ids.
	 *
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->namespace;
	}

	/**
	 * Fetches an entry from the cache.
	 *
	 * @param string $id The id of the cache entry to fetch.
	 *
	 * @return mixed The cached data or FALSE, if no cache entry exists for the given id.
	 */
	public function fetch($id)
	{
		return $this->doFetch($this->getNamespacedId($id));
	}

	/**
	 * Returns an associative array of values for keys is found in cache.
	 *
	 * @param string[] ...$keys Array of keys to retrieve from cache
	 * @return mixed[] Array of retrieved values, indexed by the specified keys.
	 *                 Values that couldn't be retrieved are not contained in this array.
	 */
	public function fetchMultiple(...$keys)
	{
		if (empty($keys)) {
			return [];
		}

		// note: the array_combine() is in place to keep an association between our $keys and the $namespacedKeys
		$namespacedKeys = array_combine($keys, array_map([$this, 'getNamespacedId'], $keys));
		$items = $this->doFetchMultiple($namespacedKeys);

		$found = [];
		// no internal array function supports this sort of mapping: needs to be iterative
		// this filters and combines keys in one pass
		foreach ($namespacedKeys as $k => $v) {
			if (isset($items[$v]) || array_key_exists($v, $items)) {
				$found[$k] = $items[$v];
			}
		}

		return $found;
	}

	/**
	 * Tests if an entry exists in the cache.
	 *
	 * @param string $id The cache id of the entry to check for.
	 *
	 * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise.
	 */
	public function contains($id)
	{
		return $this->doContains($this->getNamespacedId($id));
	}

    /**
     * Returns a boolean value indicating if the operation succeeded.
     *
     * @param array $keysAndValues  Array of keys and values to save in cache
     * @param int   $lifetime       The lifetime. If != 0, sets a specific lifetime for these
     *                              cache entries (0 => infinite lifeTime).
     *
     * @return bool TRUE if the operation was successful, FALSE if it wasn't.
     */
    public function saveMultiple(array $keysAndValues, $lifetime = 0)
    {
        $namespacedKeysAndValues = array();
        foreach ($keysAndValues as $key => $value) {
            $namespacedKeysAndValues[$this->getNamespacedId($key)] = $value;
        }

        return $this->doSaveMultiple($namespacedKeysAndValues, $lifetime);
    }

	/**
	 * Puts data into the cache.
	 *
	 * If a cache entry with the given id already exists, its data will be replaced.
	 *
	 * @param string $id The cache id.
	 * @param mixed $data The cache entry/data.
	 * @param int|null $lifetime The lifetime in number of seconds for this cache entry.
	 *                         If zero (the default), the entry never expires (although it may be deleted from the cache
	 *                         to make place for other entries).
	 *
	 * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
	 */
	public function save($id, $data, $lifetime = null)
	{
		$lifetime = $this->getLifetime($lifetime);
		return $this->doSave($this->getNamespacedId($id), $data, $lifetime);
	}

	/**
	 * @param int|null $lifetime
	 * @return int
	 */
	public function getLifetime($lifetime = null)
	{
		if ($lifetime === null) {
			$lifetime = $this->lifetime ?: 0;
		}

		return $lifetime;
	}

	/**
	 * Deletes a cache entry.
	 *
	 * @param string $id The cache id.
	 *
	 * @return bool TRUE if the cache entry was successfully deleted, FALSE otherwise.
	 *              Deleting a non-existing entry is considered successful.
	 */
	public function delete($id)
	{
		return $this->doDelete($this->getNamespacedId($id));
	}

	/**
	 * Retrieves cached information from the data store.
	 *
	 * The server's statistics array has the following values:
	 *
	 * - <b>hits</b>
	 * Number of keys that have been requested and found present.
	 *
	 * - <b>misses</b>
	 * Number of items that have been requested and not found.
	 *
	 * - <b>uptime</b>
	 * Time that the server is running.
	 *
	 * - <b>memory_usage</b>
	 * Memory used by this server to store items.
	 *
	 * - <b>memory_available</b>
	 * Memory allowed to use for storage.
	 *
	 * @since 2.2
	 *
	 * @return array|null An associative array with server's statistics if available, NULL otherwise.
	 */
	public function getStats()
	{
		return $this->doGetStats();
	}

	/**
	 * Flushes all cache entries, globally.
	 *
	 * @return bool TRUE if the cache entries were successfully flushed, FALSE otherwise.
	 */
	public function flushAll()
	{
		return $this->doFlush();
	}

	/**
	 * Deletes all cache entries in the current cache namespace.
	 *
	 * @return bool TRUE if the cache entries were successfully deleted, FALSE otherwise.
	 */
	public function deleteAll()
	{
		$namespaceCacheKey = $this->getNamespaceCacheKey();
		$namespaceVersion = $this->getNamespaceVersion() + 1;

		if ($this->doSave($namespaceCacheKey, $namespaceVersion)) {
			$this->namespaceVersion = $namespaceVersion;

			return true;
		}

		return false;
	}

	/**
	 * Prefixes the passed id with the configured namespace value.
	 *
	 * @param string $id The id to namespace.
	 *
	 * @return string The namespaced id.
	 */
	protected function getNamespacedId($id)
	{
		$version = $this->getNamespaceVersion();
		return "{$this->namespace}[$version][$id]";
	}

	/**
	 * Returns the namespace cache key.
	 *
	 * @return string
	 */
	private function getNamespaceCacheKey()
	{
		return $this->namespace . '[' . self::CACHEKEY . ']';
	}

	/**
	 * Returns the namespace version.
	 *
	 * @return integer
	 */
	private function getNamespaceVersion()
	{
		if (null !== $this->namespaceVersion) {
			return $this->namespaceVersion;
		}

		$namespaceCacheKey = $this->getNamespaceCacheKey();
		return $this->namespaceVersion = $this->doFetch($namespaceCacheKey) ?: 1;
	}

	/**
     * Default implementation of doSaveMultiple. Each driver that supports multi-put should overwrite it.
     *
     * @param array $keysAndValues  Array of keys and values to save in cache
     * @param int   $lifetime       The lifetime. If != 0, sets a specific lifetime for these
     *                              cache entries (0 => infinite lifeTime).
     *
     * @return bool TRUE if the operation was successful, FALSE if it wasn't.
     */
    protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
    {
        $success = true;

        foreach ($keysAndValues as $key => $value) {
            if (!$this->doSave($key, $value, $lifetime)) {
                $success = false;
            }
        }

        return $success;
    }

	/**
	 * Default implementation of doFetchMultiple. Each driver that supports multi-get should owerwrite it.
	 *
	 * @param array $keys Array of keys to retrieve from cache
	 * @return array Array of values retrieved for the given keys.
	 */
	protected function doFetchMultiple(array $keys)
	{
		$return = [];

		foreach ($keys as $key) {
			if (false !== ($item = $this->doFetch($key)) || $this->doContains($key)) {
				$return[$key] = $item;
			}
		}

		return $return;
	}

	/**
	 * Fetches an entry from the cache.
	 *
	 * @param string $id The id of the cache entry to fetch.
	 *
	 * @return mixed|false The cached data or FALSE, if no cache entry exists for the given id.
	 */
	abstract protected function doFetch($id);

	/**
	 * Tests if an entry exists in the cache.
	 *
	 * @param string $id The cache id of the entry to check for.
	 *
	 * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise.
	 */
	abstract protected function doContains($id);

	/**
	 * Puts data into the cache.
	 *
	 * @param string $id The cache id.
	 * @param string $data The cache entry/data.
	 * @param int $lifetime The lifetime. If != 0, sets a specific lifetime for this
	 *                           cache entry (0 => infinite lifetime).
	 *
	 * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
	 */
	abstract protected function doSave($id, $data, $lifetime = 0);

	/**
	 * Deletes a cache entry.
	 *
	 * @param string $id The cache id.
	 *
	 * @return bool TRUE if the cache entry was successfully deleted, FALSE otherwise.
	 */
	abstract protected function doDelete($id);

	/**
	 * Flushes all cache entries.
	 *
	 * @return bool TRUE if the cache entries were successfully flushed, FALSE otherwise.
	 */
	abstract protected function doFlush();

	/**
	 * Retrieves cached information from the data store.
	 *
	 * @since 2.2
	 *
	 * @return array|null An associative array with server's statistics if available, NULL otherwise.
	 */
	abstract protected function doGetStats();
}
