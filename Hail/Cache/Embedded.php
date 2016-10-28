<?php
namespace Hail\Cache;

/**
 * Class Inner
 * Use PHP's built-in cache extensions to optimize the performance of some classes.
 *
 * @package Hail\Cache
 */
class Embedded
{
    /**
     * @var string
     */
    private $type = '';

    /**
     * @var array
     */
    private $fun;

    public function __construct($ext = 'auto')
    {
        if (empty($ext) || $ext === 'none') {
            return;
        }

        $check = ['yac', 'pcache', 'xcache', 'wincache', 'apcu'];
        if (in_array($ext, $check, true)) {
            $check = [$ext];
        }

        foreach ($check as $v) {
            if (extension_loaded($v)) {
                $this->type = $v;
                break;
            }
        }

        switch($this->type) {
	        case 'yac':
		        $yac = new \Yac();
		        $this->fun = [
			        'set' => function($key, $value) use ($yac) {
				        return $yac->set($key, $value);
			        },
			        'get' => function($key) use ($yac) {
				        return $yac->get($key);
			        },
		        ];
		        return;
	        case 'pcache':
		        $this->fun = [
			        'set' => 'pcache_set',
			        'get' => 'pcache_get',
		        ];
		        return;
            case 'xcache':
                $this->fun = [
                    'set' => 'xcache_set',
                    'get' => 'xcache_get',
                ];
                return;
            case 'wincache':
                $this->fun = [
                    'set' => 'wincache_ucache_set',
                    'get' => 'wincache_ucache_get',
                ];
		        return;
	        case 'apcu':
		        $this->fun = [
			        'set' => 'apcu_store',
			        'get' => 'apcu_fetch',
		        ];
		        return;
            default:
                return;
        }
    }

    /**
     * If not found cache extension, return false
     *
     * @return bool
     */
    public function check()
    {
        return !empty($this->type);
    }

	/**
	 * Use file cache?
	 *
	 * @return bool
	 */
	public function isFile()
	{
		return $this->type === 'file';
	}

    public function set($key, $value)
    {
        if (isset($this->fun['set'])) {
            return $this->fun['set'](
	            $this->key($key), $value
            );
        }
        return false;

    }

    public function get($key)
    {
        if (isset($this->fun['get'])) {
            return $this->fun['get'](
	            $this->key($key)
            );
        }

        return false;
    }

	protected function key($key)
	{
		if ($this->type === 'yac' && strlen($key) > YAC_MAX_KEY_LEN) {
			$key = hash('sha1', $key);
		}

		return $key;
	}
}