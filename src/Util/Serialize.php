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

use Hail\Util\Closure\Serializer;

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

    private static $set = [
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

    private static $default = self::SERIALIZE;

    public static function default(?string $type): void
    {
        if ($type === null) {
            return;
        }

        self::$default = self::check($type);
    }

    private static function check(string $type): string
    {
        if (!isset(self::$set[$type])) {
            throw new \InvalidArgumentException('Serialize type not defined: ' . $type);
        }

        if (isset(self::$set[$type][self::EXTENSION])) {
            $extension = self::$set[$type][self::EXTENSION];
            if (!\extension_loaded($extension)) {
                throw new \LogicException('Extension not loaded: ' . $extension);
            }
        }

        if (isset(self::$set[$type][self::CLASSNAME])) {
            $class = self::$set[$type][self::CLASSNAME];
            if (!\class_exists($class)) {
                throw new \LogicException('Class not exists: ' . $class);
            }
        }

        return $type;
    }

    private static function run(string $functionType, $value, string $type = null)
    {
        if ($type === null || $type === self::$default) {
            $type = self::$default;
        } else {
            $type = self::check($type);
        }

        $fn = self::$set[$type][$functionType];
        if (\is_array($fn)) {
            [
                self::FUNCTION => $fn,
                self::ARGS => $args,
            ] = $fn;

            if ($args !== []) {
                return $fn($value, ...$args);
            }
        }

        return $fn($value);
    }

    /**
     * @param mixed       $value
     * @param string|null $type
     *
     * @return string
     */
    public static function encode($value, string $type = null): string
    {
        return self::run(self::ENCODER, $value, $type);
    }

    /**
     * @param string      $value
     * @param string|null $type
     *
     * @return mixed
     */
    public static function decode(string $value, string $type = null)
    {
        return self::run(self::DECODER, $value, $type);
    }

    /**
     * @param string      $value
     * @param string|null $type
     *
     * @return string
     */
    public static function encodeToBase64($value, string $type = null): string
    {
        return \base64_encode(
            self::run(self::ENCODER, $value, $type)
        );
    }

    /**
     * @param string      $value
     * @param string|null $type
     *
     * @return mixed
     */
    public static function decodeFromBase64(string $value, string $type = null)
    {
        return self::run(self::DECODER, \base64_decode($value), $type);
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public static function encodeArray(array $array, string $type = null): array
    {
        foreach ($array as &$v) {
            $v = self::run(self::ENCODER, $v, $type);
        }

        return $array;
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public static function decodeArray(array $array, string $type = null): array
    {
        foreach ($array as &$v) {
            $v = self::run(self::DECODER, $v, $type);
        }

        return $array;
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public static function encodeArrayToBase64(array $array, string $type = null): array
    {
        foreach ($array as &$v) {
            $v = \base64_encode(self::run(self::ENCODER, $v, $type));
        }

        return $array;
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public static function decodeArrayFromBase64(array $array, string $type = null): array
    {
        foreach ($array as &$v) {
            $v = self::run(self::DECODER, \base64_decode($v), $type);
        }

        return $array;
    }

    /**
     * @param \Closure    $data
     * @param string|null $type
     *
     * @return string
     */
    public static function encodeClosure(\Closure $data, string $type = null): string
    {
        return Serializer::serialize($data, $type);
    }

    /**
     * @param string      $data
     * @param string|null $type
     *
     * @return \Closure
     */
    public static function decodeClosure(string $data, string $type = null): \Closure
    {
        return Serializer::unserialize($data, $type);
    }
}

Serialize::default(\env('SERIALIZE_TYPE'));
