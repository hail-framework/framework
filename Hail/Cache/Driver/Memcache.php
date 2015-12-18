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
use \Memcache as MC;

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
 */
class Memcache extends Driver
{
    /**
     * @var Memcache|null
     */
    private $memcache;

	public function __construct($params)
	{
		parent::__construct($params);
	}

    /**
     * Sets the memcache instance to use.
     *
     * @param Memcache $memcache
     *
     * @return void
     */
    public function setMemcache(MC $memcache)
    {
        $this->memcache = $memcache;
    }

    /**
     * Gets the memcache instance used by the cache.
     *
     * @return Memcache|null
     */
    public function getMemcache()
    {
        return $this->memcache;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        return $this->memcache->get($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        $flags = null;
        $this->memcache->get($id, $flags);
        
        //if memcache has changed the value of "flags", it means the value exists
        return ($flags !== null);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifetime = 0)
    {
        if ($lifetime > 30 * 24 * 3600) {
            $lifetime = time() + $lifetime;
        }
        return $this->memcache->set($id, $data, 0, (int) $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        // Memcache::delete() returns false if entry does not exist
        return $this->memcache->delete($id) || ! $this->doContains($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        return $this->memcache->flush();
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
        $stats = $this->memcache->getStats();
        return [
	        Driver::STATS_HITS   => $stats['get_hits'],
	        Driver::STATS_MISSES => $stats['get_misses'],
	        Driver::STATS_UPTIME => $stats['uptime'],
	        Driver::STATS_MEMORY_USAGE     => $stats['bytes'],
	        Driver::STATS_MEMORY_AVAILABLE => $stats['limit_maxbytes'],
        ];
    }
}
