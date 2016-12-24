<?php
namespace Hail\Util;

// Optimize cache engine: 'auto', 'yac', 'pcache', 'wincache', 'xcache', 'apcu', 'none'
defined('HAIL_OPTIMIZE_ENGINE') || define('HAIL_OPTIMIZE_ENGINE', 'auto');

/**
 * 使用 PHP 内存缓存代替类中的通过文件获取数据，用于性能最大化
 *
 * @package Hail\Cache
 */
class Optimize
{
	/**
	 * @var string
	 */
	private static $type = '';

	private static $cache;
	private static $set;
	private static $get;
	private static $multi = false;

	public static function init()
	{
		$ext = HAIL_OPTIMIZE_ENGINE;
		if (empty($ext) || $ext === 'none') {
			return;
		}

		$check = ['yac', 'pcache', 'xcache', 'wincache', 'apcu'];
		if (in_array($ext, $check, true)) {
			$check = [$ext];
		}

		foreach ($check as $v) {
			if (extension_loaded($v)) {
				self::$type = $v;
				break;
			}
		}

		switch (self::$type) {
			case 'yac':
				self::$cache = new \Yac();
				self::$set = [self::$cache, 'set'];
				self::$get = [self::$cache, 'get'];
				self::$multi = true;

				return;
			case 'pcache':
				self::$set = 'pcache_set';
				self::$get = 'pcache_get';

				return;
			case 'xcache':
				self::$set = 'xcache_set';
				self::$get = 'xcache_get';

				return;
			case 'wincache':
				self::$set = 'wincache_ucache_set';
				self::$get = 'wincache_ucache_get';
				self::$multi = true;

				return;
			case 'apcu':
				self::$set = 'apcu_store';
				self::$get = 'apcu_fetch';
				self::$multi = true;

				return;
			default:
				return;
		}
	}

	public static function set($prefix, $key, $value)
	{
		if (self::$set === null) {
			return false;
		}

		return (self::$set)(self::key($prefix, $key), $value);
	}

	public static function setMultiple($prefix, array $array)
	{
		if (self::$set === null) {
			return false;
		}

		if (!self::$multi) {
			$return = true;
			foreach ($array as $k => $v) {
				if (false === self::set($prefix, $k, $v)) {
					$return = false;
				}
			}

			return $return;
		}

		$list = [];
		foreach ($array as $k => $v) {
			$list[self::key($prefix, $k)] = $v;
		}

		return (self::$set)($list);
	}

	public static function get($prefix, $key)
	{
		if (self::$get === null) {
			return false;
		}

		return (self::$get)(self::key($prefix, $key));
	}

	protected static function key($prefix, $key)
	{
		$key = "{$prefix}|{$key}";
		if (self::$type === 'yac' && strlen($key) > \YAC_MAX_KEY_LEN) {
			$key = sha1($key);
		}

		return $key;
	}
}

Optimize::init();