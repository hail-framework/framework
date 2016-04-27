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
 * YAC cache provider.
 *
 * @author FlyingHail <flyinghail@msn.com>
 */
class Yac extends Driver
{
	private $yac;

	public function __construct($params)
	{
		$this->yac = new \Yac();
		parent::__construct($params);
	}

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        return $this->yac->get($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        return $this->yac->get($id) !== false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifetime = 0)
    {
        return $this->yac->set($id, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        return $this->yac->delete($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        return $this->yac->flush();
    }

	/**
	 * {@inheritdoc}
	 */
	protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
	{
		return $this->yac->set($keysAndValues, $lifetime);
	}

    /**
     * {@inheritdoc}
     */
    protected function doFetchMultiple(array $keys)
    {
        return $this->yac->get($keys);
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
        $info = $this->yac->info();
        return [
            Driver::STATS_HITS             => $info['hits'],
	        Driver::STATS_MISSES           => $info['miss'],
	        Driver::STATS_MEMORY_USAGE     => $info['memory_size']
        ];
    }
}
