<?php
namespace Hail\Utils;

/**
 * 小数组
 * 尺寸:     msgpack < igbinary < json < hprose < swoole < serialize
 * 序列化速度:   swoole << serialize < msgpack < json << igbinary < hprose
 * 反序列化速度: swoole << igbinary < msgpack < serialize < hprose << json
 *
 * 大数组
 * 尺寸:     igbinary < hprose < msgpack < json << swoole < serialize
 * 序列化速度:   swoole << msgpack < serialize < igbinary =< json < hprose
 * 反序列化速度: swoole << igbinary < hprose < serialize < msgpack << json
 */
use Hail\Exception;

defined('HAIL_SERIALIZE') || define('HAIL_SERIALIZE', 'serialize');

/**
 * Class Serialize
 *
 * @package Hail\Utils
 * @author  Hao Feng <flyinghail@msn.com>
 *
 * @method static string encode($value, string $engine = HAIL_SERIALIZE)
 * @method static string decode($value, string $engine = HAIL_SERIALIZE)
 * @method static string encodeToString($value, string $engine = HAIL_SERIALIZE)
 * @method static string decodeFromBase64($value, string $engine = HAIL_SERIALIZE)
 * @method static string encodeArray(array $array, string $engine = HAIL_SERIALIZE)
 * @method static string decodeArray(array $array, string $engine = HAIL_SERIALIZE)
 * @method static string encodeArrayToString(array $array, string $engine = HAIL_SERIALIZE)
 * @method static string decodeArrayFromBase64(array $array, string $engine = HAIL_SERIALIZE)
 */
class Serialize
{
	private static $set = [
		'msgpack' => [
			'ext' => 'msgpack',
			'type' => 'bin',
			'encoder' => 'msgpack_pack',
			'decoder' => 'msgpack_unpack',
		],
		'swoole' => [
			'ext' => 'swoole_serialize',
			'type' => 'bin',
			'encoder' => 'swoole_serialize',
			'decoder' => 'swoole_unserialize',
		],
		'igbinary' => [
			'ext' => 'igbinary',
			'type' => 'bin',
			'encoder' => 'igbinary_serialize',
			'decoder' => 'igbinary_unserialize',
		],
		'hprose' => [
			'ext' => 'hprose',
			'type' => 'text',
			'encoder' => 'hprose_serialize',
			'decoder' => 'hprose_unserialize',
		],
		'json' => [
			'type' => 'text',
			'encoder' => 'Hail\Utils\Json::encode',
			'decoder' => 'Hail\Utils\Json::decode',
		],
		'serialise' => [
			'type' => 'text',
			'encoder' => 'serialise',
			'decoder' => 'unserialise',
		],
	];

	private $engine;

	public function __construct($engine = HAIL_SERIALIZE)
	{
		if (!isset(self::$set[$engine])) {
			throw new Exception\InvalidArgument("Serialize engine $engine not defined");
		}

		$set = self::$set[$engine];
		if (isset($set['ext']) && !extension_loaded($set['ext'])) {
			throw new Exception\InvalidArgument("Extension {$set['ext']} not loaded");
		}

		$this->engine = $engine;
	}

	public function __call($name, $arguments)
	{
		$name = '_' . $name;
		if (isset($arguments[0]) && method_exists(__CLASS__, $name)) {
			if (isset($arguments[1])) {
				return self::$name($arguments[0], $arguments[1]);
			}

			return self::$name($arguments[0], $this->engine);
		}

		throw new \RuntimeException("Method $name not defined");
	}

	public static function __callStatic($name, $arguments)
	{
		$name = '_' . $name;
		if (isset($arguments[0]) && method_exists(__CLASS__, $name)) {
			if (isset($arguments[1])) {
				return self::$name($arguments[0], $arguments[1]);
			}

			return self::$name($arguments[0]);
		}

		throw new \RuntimeException("Method $name not defined");
	}

	protected static function _encode($value, $engine = HAIL_SERIALIZE)
	{
		return (self::$set[$engine]['encoder'])($value);
	}

	protected static function _decode($value, $engine = HAIL_SERIALIZE)
	{
		$return = @(self::$set[$engine]['decoder'])($value);

		return $return ?: null;
	}

	protected static function _encodeToString($value, $engine = HAIL_SERIALIZE)
	{
		if (self::$set[$engine]['type'] === 'text') {
			return self::_encode($value);
		}

		return base64_encode(
			self::_encode($value)
		);
	}

	protected static function _decodeFromBase64($value, $engine = HAIL_SERIALIZE)
	{
		if (self::$set[$engine]['type'] === 'text') {
			return self::_decode($value);
		}

		return self::_decode(
			base64_decode($value)
		);
	}

	protected static function _encodeArray(array $array, $engine = HAIL_SERIALIZE)
	{
		return array_map(self::$set[$engine]['encoder'], $array);
	}

	protected static function _decodeArray(array $array, $engine = HAIL_SERIALIZE)
	{
		$return = [];
		foreach ($array as $k => $v) {
			$return[$k] = self::_decode($v, $engine);
		}

		return $return;
	}

	protected static function _encodeArrayToString(array $array, $engine = HAIL_SERIALIZE)
	{
		$return = [];
		foreach ($array as $k => $v) {
			$return[$k] = self::_encodeToString($v, $engine);
		}

		return $return;
	}

	protected static function _decodeArrayFromBase64(array $array, $engine = HAIL_SERIALIZE)
	{
		$return = [];
		foreach ($array as $k => $v) {
			$return[$k] = self::_decodeFromBase64($v, $engine);
		}

		return $return;
	}
}