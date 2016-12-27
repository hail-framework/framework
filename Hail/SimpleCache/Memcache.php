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
 * Memcache cache provider.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author David Abdemoulaie <dave@hobodave.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class Memcache extends AbtractDriver
{
	/**
	 * @var \Memcache|null
	 */
	private $memcache;

	public function __construct($params)
	{
		if (!class_exists('Memcache', false)) {
			throw new \RuntimeException('No memcache extension available.');
		}

		$memcache = new \Memcache();

		if (isset($params['servers'])) {
			$paramServers = (array) $params['servers'];
			unset($params['servers']);

			foreach ($paramServers as $server) {
				$host = $server['host'] ?? $server[0] ?? '127.0.0.1';
				$port = $server['port'] ?? $server[1] ?? 11211;
				$weight = $server['weight'] ?? $server[2] ?? null;

				if (is_int($weight)) {
					$memcache->addServer($host, $port, true, $weight);
				} else {
					$memcache->addServer($host, $port);
				}
			}
		} else {
			$memcache->addServer('127.0.0.1', 11211);
		}

		$this->memcache = $memcache;

		parent::__construct($params);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		return $this->memcache->get($key);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetMultiple(array $keys)
	{
		return $this->memcache->get($keys) ?: [];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		$flags = null;
		$this->memcache->get($key, $flags);

		//if memcache has changed the value of "flags", it means the value exists
		return ($flags !== null);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		if ($ttl > 2592000) {
			$ttl = NOW + $ttl;
		}

		return $this->memcache->set($key, $value, 0, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		// Memcache::delete() returns false if entry does not exist
		$this->memcache->delete($key);
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		return $this->memcache->flush();
	}
}
