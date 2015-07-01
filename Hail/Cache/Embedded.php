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
        if (empty($ext) || $ext == 'none') {
            return;
        }

        $check = array('apcu', 'apc', 'xcache', 'yac', 'pcache', 'wincache');
        if (in_array($ext, $check, true)) {
            $check = array($ext);
        }

        foreach ($check as $v) {
            if (extension_loaded($v)) {
                $this->type = $v;
                break;
            }
        }

        switch($this->type) {
            case 'apcu':
                $this->fun = [
                    'set' => 'apcu_store',
                    'get' => 'apcu_fetch',
                ];
                return;
            case 'apc':
                $this->fun = [
                    'set' => 'apc_store',
                    'get' => 'apc_fetch',
                ];
                return;
            case 'xcache':
                $this->fun = [
                    'set' => 'xcache_set',
                    'get' => 'xcache_get',
                ];
                return;
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
            case 'wincache':
                $this->fun = [
                    'set' => 'wincache_ucache_set',
                    'get' => 'wincache_ucache_get',
                ];
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
            return $this->fun['set']($key, $value);
        }
        return false;

    }

    public function get($key)
    {
        if (isset($this->fun['get'])) {
            return $this->fun['get']($key);
        }

        return false;
    }
}