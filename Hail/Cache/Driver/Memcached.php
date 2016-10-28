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

namespace Hail\Cache\Driver;

use Hail\Cache\Driver;

/**
 * Memcached cache provider.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author David Abdemoulaie <dave@hobodave.com>
 * @author FlyingHail <flyinghail@msn.com>
 */
class Memcached extends Driver
{
	/**
	 * @var \Memcached|null
	 */
	private $memcached;

	public function __construct($params)
	{
		if (!class_exists('Memcached', false)) {
			throw new \RuntimeException('No memcache extension available.');
		}

		if (isset($params['servers'])) {
			$paramServers = (array) $params['servers'];
			unset($params['servers']);

			$servers = [];
			foreach ($paramServers as $server) {
				$host = $server['host'] ?? $server[0] ?? '127.0.0.1';
				$port = $server['port'] ?? $server[1] ?? 11211;
				$weight = $server['weight'] ?? $server[2] ?? null;
				$servers[] = [$host, $port, $weight];
			}
		} else {
			$servers = [['127.0.0.1', 11211]];
		}

		$memcached = new \Memcached();
		if (empty($memcached->getServerList())) {
			$memcached->addServers($servers);
		}

		foreach ($params as $name => $value) {
			$name = strtoupper($name);
			switch ($name) {
				case 'HASH':
					$value = strtoupper($value);
					if (!defined('\Memcached::HASH_' . $value)) {
						throw new \RuntimeException('Memcached option ' . $name . ' requires valid memcache hash option value');
					}
					$value = constant('\Memcached::HASH_' . $value);
				break;
				case 'DISTRIBUTION':
					$value = strtoupper($value);
					if (!defined('\Memcached::DISTRIBUTION_' . $value)) {
						throw new \RuntimeException('Memcached option ' . $name . ' requires valid memcache distribution option value');
					}
					$value = constant('\Memcached::DISTRIBUTION_' . $value);
				break;
				case 'SERIALIZER':
					$value = strtoupper($value);
					if (!defined('\Memcached::SERIALIZER_' . $value)) {
						throw new \RuntimeException('Memcached option ' . $name . ' requires valid memcache serializer option value');
					}
					$value = constant('\Memcached::SERIALIZER_' . $value);
				break;
				case 'SOCKET_SEND_SIZE':
				case 'SOCKET_RECV_SIZE':
				case 'CONNECT_TIMEOUT':
				case 'RETRY_TIMEOUT':
				case 'SEND_TIMEOUT':
				case 'RECV_TIMEOUT':
				case 'POLL_TIMEOUT':
				case 'SERVER_FAILURE_LIMIT':
					if (!is_numeric($value)) {
						throw new \RuntimeException('Memcached option ' . $name . ' requires numeric value');
					}
				break;
				case 'PREFIX_KEY':
					if (!is_string($value)) {
						throw new \RuntimeException('Memcached option ' . $name . ' requires string value');
					}
				break;
				case 'COMPRESSION':
				case 'LIBKETAMA_COMPATIBLE':
				case 'BUFFER_WRITES':
				case 'BINARY_PROTOCOL':
				case 'NO_BLOCK':
				case 'TCP_NODELAY':
				case 'CACHE_LOOKUPS':
					if (!is_bool($value)) {
						throw new \RuntimeException('Memcached option ' . $name . ' requires boolean value');
					}
				break;
				default:
					continue;
			}

			if (!@$memcached->setOption(constant('\Memcached::OPT_' . $name), $value)) {
				throw new \RuntimeException('Memcached option Memcached::OPT_' . $name . ' not accepted by memcached extension.');
			}
		}

		$this->memcached = $memcached;

		parent::__construct($params);
	}

	/**
	 * Sets the memcache instance to use.
	 *
	 * @param \Memcached $memcached
	 *
	 * @return void
	 */
	public function setMemcached(\Memcached $memcached)
	{
		$this->memcached = $memcached;
	}

	/**
	 * Gets the memcached instance used by the cache.
	 *
	 * @return \Memcached|null
	 */
	public function getMemcached()
	{
		return $this->memcached;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		return $this->memcached->get($id);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
	{
		if ($lifetime > 30 * 24 * 3600) {
			$lifetime = NOW + $lifetime;
		}

		return $this->memcached->setMulti($keysAndValues, null, $lifetime);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetchMultiple(array $keys)
	{
		return $this->memcached->getMulti($keys) ?: [];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		return false !== $this->memcached->get($id)
		|| $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifetime = 0)
	{
		if ($lifetime > 30 * 24 * 3600) {
			$lifetime = NOW + $lifetime;
		}

		return $this->memcached->set($id, $data, (int) $lifetime);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		return $this->memcached->delete($id)
		|| $this->memcached->getResultCode() === \Memcached::RES_NOTFOUND;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDeleteMultiple(array $keys)
	{
		return $this->memcached->deleteMulti($keys)
		|| $this->memcached->getResultCode() === \Memcached::RES_NOTFOUND;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		return $this->memcached->flush();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		$stats = $this->memcached->getStats();
		$servers = $this->memcached->getServerList();
		$key = $servers[0]['host'] . ':' . $servers[0]['port'];
		$stats = $stats[$key];

		return [
			Driver::STATS_HITS => $stats['get_hits'],
			Driver::STATS_MISSES => $stats['get_misses'],
			Driver::STATS_UPTIME => $stats['uptime'],
			Driver::STATS_MEMORY_USAGE => $stats['bytes'],
			Driver::STATS_MEMORY_AVAILABLE => $stats['limit_maxbytes'],
		];
	}
}
