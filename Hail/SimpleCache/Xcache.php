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
 * Xcache cache driver.
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
class Xcache extends AbtractAdapter
{
    /**
     * {@inheritdoc}
     */
    protected function doGet(string $key)
    {
        return xcache_isset($key) ? xcache_get($key) : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function doHas(string $key)
    {
        return xcache_isset($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSet(string $key, $value, int $ttl = 0)
    {
        return xcache_set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(string $key)
    {
        return xcache_unset($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear()
    {
	    if (ini_get('xcache.admin.enable_auth')) {
		    throw new \BadMethodCallException(
			    'To use all features of \Hail\SimpleCache\Xcache, '
			    . 'you must set "xcache.admin.enable_auth" to "Off" in your php.ini.'
		    );
	    }

        xcache_clear_cache(\XC_TYPE_VAR);

        return true;
    }
}
