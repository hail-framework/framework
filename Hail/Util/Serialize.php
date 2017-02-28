<?php
namespace Hail\Util;

/**
 * 小数组
 * 尺寸:     msgpack < swoole = swoole(fast) < igbinary < json < hprose < serialize
 * 序列化速度:   swoole(fast) << serialize < msgpack < json < swoole << igbinary << hprose
 * 反序列化速度: swoole ~ swoole(fast) << igbinary < msgpack < serialize < hprose << json
 *
 * 大数组
 * 尺寸:     swoole < igbinary << hprose << msgpack < swoole(fast) < json << serialize
 * 序列化速度:   swoole(fast) < swoole << msgpack < serialize < igbinary =< json < hprose
 * 反序列化速度: swoole(fast) < swoole << igbinary < hprose < serialize < msgpack << json
 *
 */

/**
 * Class Serialize
 *
 * @package Hail\Util
 * @author  Hao Feng <flyinghail@msn.com>
 */
class Serialize
{
	const EXT_SWOOLE = 'swoole';
	const EXT_SWOOLE_FAST = 'swoole_fast';
	const EXT_MSGPACK = 'msgpack';
	const EXT_IGBINARY = 'igbinary';
	const EXT_HPROSE = 'hprose';
	const EXT_JSON = 'json';
	const EXT_SERIALIZE = 'serialize';

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
			'encoder' => 'swoole_pack',
			'decoder' => 'swoole_unpack',
		],
		'swoole_fast' => [
			'ext' => 'swoole_serialize',
			'type' => 'bin',
			'encoder' => 'swoole_fast_pack',
			'decoder' => 'swoole_unpack',
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
			'encoder' => 'Hail\Util\Json::encode',
			'decoder' => 'Hail\Util\Json::decode',
		],
		'serialise' => [
			'type' => 'text',
			'encoder' => 'serialise',
			'decoder' => 'unserialise',
		],
	];

	private $extension;
	private $type;
	private $encoder;
	private $decoder;

	public function __construct(string $type)
	{
		$this->setExtension($type);
	}

	/**
	 * @param string $type
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 */
	public function setExtension(string $type)
	{
		if (!isset(self::$set[$type])) {
			throw new \InvalidArgumentException("Serialize type $type not defined");
		}

		$set = self::$set[$type];
		if (isset($set['ext']) && !extension_loaded($set['ext'])) {
			throw new \LogicException("Extension {$set['ext']} not loaded");
		}

		$this->extension = $type;
		$this->type = $set['type'];
		$this->encoder = $set['encoder'];
		$this->decoder = $set['decoder'];

		return $this;
	}

	/**
	 * @param string $type
	 *
	 * @return Serialize
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 */
	public function withExtension(string $type)
	{
		$clone = clone $this;

		return $clone->setExtension($type);
	}

	/**
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function encode($value)
	{
		$fn = $this->encoder;

		return $fn($value);
	}

	/**
	 * @param string $value
	 *
	 * @return mixed
	 */
	public function decode($value)
	{
		$fn = $this->decoder;

		return $fn($value);
	}

	/**
	 * @param string $value
	 *
	 * @return string
	 */
	public function encodeToStr($value)
	{
		if ($this->type === 'text') {
			return $this->encode($value);
		}

		return base64_encode(
			$this->encode($value)
		);
	}

	/**
	 * @param string $value
	 *
	 * @return mixed
	 */
	public function decodeFromStr($value)
	{
		if ($this->type === 'text') {
			return $this->decode($value);
		}

		return $this->decode(
			base64_decode($value)
		);
	}

	/**
	 * @param array $array
	 *
	 * @return array
	 */
	public function encodeArray(array $array)
	{
		return array_map($this->encoder, $array);
	}

	/**
	 * @param array $array
	 *
	 * @return array
	 */
	public function decodeArray(array $array)
	{
		return array_map($this->decoder, $array);
	}

	/**
	 * @param array $array
	 *
	 * @return array
	 */
	protected function encodeArrayToStr(array $array)
	{
		if ($this->type === 'text') {
			return array_map($this->encoder, $array);
		}

		return array_map([$this, 'encodeToStr'], $array);
	}

	/**
	 * @param array $array
	 *
	 * @return array
	 */
	public function decodeArrayFromStr(array $array)
	{
		if ($this->type === 'text') {
			return array_map($this->decoder, $array);
		}

		return array_map([$this, 'decodeFromStr'], $array);
	}
}