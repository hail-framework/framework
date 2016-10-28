<?php
namespace Hail\Cache;

trait EmbeddedTrait
{
    /**
     * cache engine function setting
     *
     * @var mixed
     */
    protected static $_cache;

	/**
	 * EmbeddedTrait constructor.
	 *
	 */
	public function __construct()
	{
		self::initCache();
	}

	protected static function initCache()
    {
	    if (self::$_cache !== null){
		    return;
	    }

        $cache = \DI::embedded();

        if ($cache->check()) {
            self::$_cache = $cache;
        }
    }

	protected function cacheGet($key)
    {
        if (self::$_cache !== null) {
            return self::$_cache->get(
                $this->cacheName($key)
            );
        }

        return null;
    }

	protected function cacheSet($key, $value)
    {
        if (self::$_cache !== null) {
	        self::$_cache->set(
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
	protected function cacheName($key)
    {
        return __CLASS__ . '/' . $key;
    }

	protected function cacheUpdateCheck($key, $file)
	{
		if (EMBEDDED_CACHE_CHECK_DELAY === 0) {
			return true;
		}

		/**
		 * @var null|array $check
		 */
		$check = $this->cacheGet($key . '/time');
		if ($check !== false) {
			if (NOW >= ($check[0] + EMBEDDED_CACHE_CHECK_DELAY)) {
				if (!file_exists($file) || filemtime($file) !== $check[1]) {
					return false;
				}

				$check[0] = NOW;
				$this->cacheSet($key . '/time', $check);
			}
			return true;
		}

		return false;
	}

	protected function cacheSetTime($key, $file)
	{
		$this->cacheSet($key . '/time', [NOW, filemtime($file)]);
	}
}