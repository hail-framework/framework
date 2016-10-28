<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/14 0014
 * Time: 12:33
 */

namespace Hail\Utils;

use Hail\Facades\Config;

class Serialize
{
	private static $mode = 'php_serialize';

	private static $encode = 'serialize';
	private static $decode = 'unserialize';

	public static function init()
	{
		$type = Config::get('app.serialize');

		switch ($type) {
			case 'msgpack':
				if (extension_loaded('msgpack')) {
					self::$mode = 'msgpack';
					self::$encode = 'msgpack_pack';
					self::$decode = 'msgpack_unpack';
				}
			break;

			case 'igbinary':
				if (extension_loaded('igbinary')) {
					self::$mode = 'igbinary';
					self::$encode = 'igbinary_serialize';
					self::$decode = 'igbinary_unserialize';
				}
			break;
		}
	}

	public static function encode($value)
	{
		return (self::$encode)($value);
	}

	public static function decode($value)
	{
		$return = @(self::$decode)($value);
		return $return ?: false;
	}

	public static function encodeToString($value)
	{
		if (self::$mode === 'php_serialize') {
			return serialize($value);
		}

		return base64_encode(
			self::encode($value)
		);
	}

	public static function decodeFromBase64($value)
	{
		if (self::$mode === 'php_serialize') {
			return unserialize($value);
		}

		return self::decode(
			base64_decode($value)
		);
	}

	public static function encodeArray($array) {
		return array_map(self::$encode, $array);
	}

	public static function decodeArray($array) {
		return array_map([self::class, 'decode'], $array);
	}

	public static function decodeArrayFromBase64($array) {
		return array_map([self::class, 'decodeFromBase64'], $array);
	}
}

Serialize::init();