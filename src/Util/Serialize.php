<?php

namespace Hail\Util;

\defined('SWOOLE_FAST_PACK') || \define('SWOOLE_FAST_PACK', 1);

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
 * @author  Feng Hao <flyinghail@msn.com>
 */
class Serialize
{
    public const SWOOLE = 'swoole';
    public const SWOOLE_FAST = 'swoole_fast';
    public const MSGPACK = 'msgpack';
    public const IGBINARY = 'igbinary';
    public const HPROSE = 'hprose';
    public const JSON = 'json';
    public const SERIALIZE = 'serialize';

    private const CLASSNAME = 'class';
    private const EXTENSION = 'ext';
    private const ENCODER = 'encoder';
    private const DECODER = 'decoder';
    private const FUNCTION = 'function';
    private const ARGS = 'args';

    private const SET = [
        self::MSGPACK => [
            self::EXTENSION => 'msgpack',
            self::ENCODER => '\msgpack_pack',
            self::DECODER => '\msgpack_unpack',
        ],
        self::SWOOLE => [
            self::CLASSNAME => \Swoole\Serialize::class,
            self::ENCODER => '\Swoole\Serialize::pack',
            self::DECODER => '\Swoole\Serialize::unpack',
        ],
        self::SWOOLE_FAST => [
            self::CLASSNAME => \Swoole\Serialize::class,
            self::ENCODER => [
                self::FUNCTION => '\Swoole\Serialize::pack',
                self::ARGS => [\SWOOLE_FAST_PACK],
            ],
            self::DECODER => '\Swoole\Serialize::unpack',
        ],
        self::IGBINARY => [
            self::EXTENSION => 'igbinary',
            self::ENCODER => '\igbinary_serialize',
            self::DECODER => '\igbinary_unserialize',
        ],
        self::HPROSE => [
            self::EXTENSION => 'hprose',
            self::ENCODER => '\hprose_serialize',
            self::DECODER => '\hprose_unserialize',
        ],
        self::JSON => [
            self::EXTENSION => null,
            self::ENCODER => '\Hail\Util\Json::encode',
            self::DECODER => '\Hail\Util\Json::decode',
        ],
        self::SERIALIZE => [
            self::EXTENSION => null,
            self::ENCODER => '\serialize',
            self::DECODER => '\unserialize',
        ],
    ];

    /**
     * @var string
     */
    private $default;

    /**
     * @var string
     */
    private $type;

    private $once = false;

    public function __construct(?string $type)
    {
        $this->type($type ?? \env('SERIALIZE_TYPE'));
    }

    public function type(string $type): self
    {
        $this->type = $this->check($type);

        return $this;
    }

    public function once(string $type): self
    {
        $this->default = $this->type;
        $this->type = $this->check($type);
        $this->once = true;

        return $this;
    }

    private function check(string $type): string
    {
        if (!isset(self::SET[$type])) {
            throw new \InvalidArgumentException('Serialize type not defined: ' . $type);
        }

        if (isset(self::SET[$type][self::EXTENSION])) {
            $extension = self::SET[$type][self::EXTENSION];
            if (!\extension_loaded($extension)) {
                throw new \LogicException('Extension not loaded: ' . $extension);
            }
        }

        if (isset(self::SET[$type][self::CLASSNAME])) {
            $class = self::SET[$type][self::CLASSNAME];
            if (!\class_exists($class)) {
                throw new \LogicException('Class not exists: ' . $class);
            }
        }

        return $type;
    }

    /**
     * @param string $functionType
     * @param mixed  $value
     *
     * @return mixed
     */
    private function run(string $functionType, $value)
    {
        $fn = self::SET[$this->type][$functionType];
        $args = [];
        if (\is_array($fn)) {
            [
                self::FUNCTION => $fn,
                self::ARGS => $args,
            ] = $fn;
        }

        if ($args !== []) {
            $return = $fn($value, ...$args);
        } else {
            $return = $fn($value);
        }

        if ($this->once) {
            $this->type = $this->default;
            $this->once = false;
            $this->default = null;
        }

        return $return;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public function encode($value): string
    {
        return $this->run(self::ENCODER, $value);
    }

    /**
     * @param string      $value
     *
     * @return mixed
     */
    public function decode(string $value)
    {
        return $this->run(self::DECODER, $value);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function encodeToBase64($value): string
    {
        return \base64_encode(
            $this->run(self::ENCODER, $value)
        );
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    public function decodeFromBase64(string $value)
    {
        return $this->run(self::DECODER, \base64_decode($value));
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public function encodeArray(array $array): array
    {
        foreach ($array as &$v) {
            $v = $this->run(self::ENCODER, $v);
        }

        return $array;
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public function decodeArray(array $array): array
    {
        foreach ($array as &$v) {
            $v = $this->run(self::DECODER, $v);
        }

        return $array;
    }

    /**
     * @param array       $array
     *
     * @return array
     */
    public function encodeArrayToBase64(array $array): array
    {
        foreach ($array as &$v) {
            $v = \base64_encode($this->run(self::ENCODER, $v));
        }

        return $array;
    }

    /**
     * @param array       $array
     *
     * @return array
     */
    public function decodeArrayFromBase64(array $array): array
    {
        foreach ($array as &$v) {
            $v = $this->run(self::DECODER, \base64_decode($v));
        }

        return $array;
    }
}

