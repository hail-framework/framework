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

namespace Hail\SimpleCache;


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
 * @author Hao Feng <flyinghail@msn.com>
 */
class Memcached extends AbtractAdapter
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
					continue 2;
			}

			if (!@$memcached->setOption(constant('\Memcached::OPT_' . $name), $value)) {
				throw new \RuntimeException('Memcached option Memcached::OPT_' . $name . ' not accepted by memcached extension.');
			}
		}

		$this->memcached = $memcached;

		parent::__construct($params);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		$return = $this->memcached->get($key);
		return $return === false ? null : $return;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		if ($ttl > 2592000) {
			$ttl = NOW + $ttl;
		}

		return $this->memcached->setMulti($values, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetMultiple(array $keys)
	{
		return $this->memcached->getMulti($keys) ?: [];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		return false !== $this->memcached->get($key)
		|| $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		if ($ttl > 2592000) {
			$ttl = NOW + $ttl;
		}

		return $this->memcached->set($key, $value, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		return $this->memcached->delete($key)
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
	protected function doClear()
	{
		return $this->memcached->flush();
	}
}
