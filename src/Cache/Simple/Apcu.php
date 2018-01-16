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

namespace Hail\Cache\Simple;

/**
 * APCu cache provider.
 *
 * @link   www.doctrine-project.org
 * @since  1.6
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Feng Hao <flyinghail@msn.com>
 */
class Apcu extends AbstractAdapter
{
    /**
     * {@inheritdoc}
     */
    protected function doGet(string $key)
    {
    	$value = \apcu_fetch($key);
        return $value === false ? null : $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function doHas(string $key)
    {
        return \apcu_exists($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSet(string $key, $value, int $ttl = 0)
    {
        return \apcu_store($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(string $key)
    {
        // apcu_delete returns false if the id does not exist
	    \apcu_delete($key);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear()
    {
        return \apcu_clear_cache();
    }

	/**
	 * {@inheritdoc}
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		$result = \apcu_store($values, null, $ttl);

		return !($result === false || \count($result) > 0);
	}

    /**
     * {@inheritdoc}
     */
    protected function doGetMultiple(array $keys)
    {
        return \apcu_fetch($keys) ?: [];
    }
}
