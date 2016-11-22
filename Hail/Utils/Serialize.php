<?php
namespace Hail\Utils;

/**
 * 小数组
 * 压缩尺寸:     msgpack < igbinary < json < swoole < serialize
 * 序列化速度:   swoole << serialize < msgpack < json < igbinary
 * 反序列化速度: swoole << igbinary < msgpack < serialize << json
 *
 * 大数组
 * 压缩尺寸:     igbinary << msgpack < json << swoole < serialize
 * 序列化速度:   swoole << msgpack < serialize < igbinary ≈ json
 * 反序列化速度: swoole << igbinary < serialize < msgpack << json
 *
 * serialize 和 json 不需要安装额外的扩展
 */
defined('HAIL_SERIALIZE') || define('HAIL_SERIALIZE', 'serialize');

/**
 * Class Serialize
 *
 * @package Hail\Utils
 * @author Hao Feng <flyinghail@msn.com>
 */
class Serialize
{
	private static $mode;
	private static $encode;
	private static $decode;

	public static function init()
	{
		$type = HAIL_SERIALIZE;

		switch ($type) {
			case 'msgpack':
				if (extension_loaded('msgpack')) {
					self::$mode = 'msgpack';
					self::$encode = 'msgpack_pack';
					self::$decode = 'msgpack_unpack';
				}
				break;

			case 'swoole':
				if (extension_loaded('swoole_serialize')) {
					self::$mode = 'swoole_serialize';
					self::$encode = 'swoole_serialize';
					self::$decode = 'swoole_unserialize';
				}
				break;

			case 'igbinary':
				if (extension_loaded('igbinary')) {
					self::$mode = 'igbinary';
					self::$encode = 'igbinary_serialize';
					self::$decode = 'igbinary_unserialize';
				}
				break;

			case 'json':
				self::$mode = 'json';
				self::$encode = 'Hail\Utils\Json::encode';
				self::$decode = 'Hail\Utils\Json::decode';
				break;

			case 'serialise':
			default:
				self::$mode = 'serialize';
				self::$encode = 'serialize';
				self::$decode = 'unserialize';
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
		if (in_array(self::$mode, ['serialize', 'json'], true)) {
			return self::encode($value);
		}

		return base64_encode(
			self::encode($value)
		);
	}

	public static function decodeFromBase64($value)
	{
		if (in_array(self::$mode, ['serialize', 'json'], true)) {
			return self::decode($value);
		}

		return self::decode(
			base64_decode($value)
		);
	}

	public static function encodeArray($array)
	{
		return array_map(self::$encode, $array);
	}

	public static function decodeArray($array)
	{
		return array_map([self::class, 'decode'], $array);
	}

	public static function decodeArrayFromBase64($array)
	{
		return array_map([self::class, 'decodeFromBase64'], $array);
	}
}

Serialize::init();