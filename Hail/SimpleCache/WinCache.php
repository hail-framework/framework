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
 * WinCache cache provider.
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
class WinCache extends AbstractAdapter
{
	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		$value = wincache_ucache_get($key, $success);
		if ($success === false) {
			return null;
		}

		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		return wincache_ucache_exists($key);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		return wincache_ucache_set($key, $value, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		$result = wincache_ucache_set($values, null, $ttl);

		if ($result === false || count($result)) {
			return false;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		return wincache_ucache_delete($key);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		return wincache_ucache_clear();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetMultiple(array $keys)
	{
		return wincache_ucache_get($keys);
	}
}
