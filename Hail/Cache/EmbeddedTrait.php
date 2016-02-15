<?php
namespace Hail\Cache;

trait EmbeddedTrait
{
    /**
     * cache engine function setting
     *
     * @var mixed
     */
    protected $cache;

	/**
	 * EmbeddedTrait constructor.
	 *
	 * @param null|\Hail\DI $di
	 */
	public function __construct($di = null)
	{
		$this->initCache($di);
	}

	/**
	 * @param null|\Hail\DI $di
	 */
    public function initCache($di = null)
    {
        if (null === $di) {
            $cache = \DI::embedded();
        } else {
            $cache = $di['embedded'];
        }

        if ($cache->check()) {
            $this->cache = $cache;
        }
    }

    public function getCache($key)
    {
        if (!empty($this->cache)) {
            return $this->cache->get(
                $this->cacheName($key)
            );
        }

        return null;
    }

    public function setCache($key, $value)
    {
        if (!empty($this->cache)) {
            $this->cache->set(
                $this->cacheName($key), $value
            );
        }

        return $value;
    }

    /**
     * Cache name
     *
     * @param $key string
     * @return string
     */
    public function cacheName($key)
    {
        return __CLASS__ . '/' . $key;
    }

	public function updateCheck($key, $file)
	{
		if (EMBEDDED_CACHE_CHECK_DELAY === 0) {
			return true;
		}

		/**
		 * @var null|array $check
		 */
		$check = $this->getCache($key . '/time');
		if ($check) {
			if (NOW >= ($check[0] + EMBEDDED_CACHE_CHECK_DELAY)) {
				if (!file_exists($file) || filemtime($file) !== $check[1]) {
					return false;
				}

				$check[0] = NOW;
				$this->setCache($key . '/time', $check);
			}
			return true;
		}

		return false;
	}

	public function setTime($key, $file)
	{
		$this->setCache($key . '/time', [NOW, filemtime($file)]);
	}
}