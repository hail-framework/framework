<?php

namespace Hail\Serialize;

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
 * swoole serialize 尺寸小，速度快但是官方已经放弃继续支持 PHP7.3+
 */

/**
 * Class Serialize
 *
 * @package Hail\Util
 * @author  Feng Hao <flyinghail@msn.com>
 *
 * @property-read MsgPack   $msgpak
 * @property-read Igbinary  $igbinary
 * @property-read Hprose    $hprose
 * @property-read Json      $json
 * @property-read Serialize $php
 * @property-read Yaml      $yaml
 * @method MsgPack msgpak()
 * @method Igbinary igbinary()
 * @method Hprose hprose()
 * @method Json json()
 * @method Serialize php()
 * @method Yaml yaml()
 */
final class Serializer
{
    public const MAP = [
        'msgpack' => MsgPack::class,
        'igbinary' => Igbinary::class,
        'hprose' => Hprose::class,
        'json' => Json::class,
        'php' => Serialize::class,
        'yaml' => Yaml::class,
    ];

    /**
     * @var string
     */
    private $default;


    public function __construct(?string $type)
    {
        $type = $type ?? \env('SERIALIZE_TYPE') ?? 'php';

        if (!isset(self::MAP[$type])) {
            throw new \InvalidArgumentException('Serialize type not defined: ' . $type);
        }

        $this->default = $type;
    }

    public function __get($name)
    {
        if (!isset(self::MAP[$name])) {
            throw new \InvalidArgumentException('Serialize type not defined: ' . $name);
        }

        return $this->$name = new (self::MAP[$name])();
    }

    public function __call($name, $arguments)
    {
        if (isset(static::MAP[$name])) {
            return $this->$name;
        }

        throw new \BadMethodCallException('Method not defined: ' . $name);
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public function encode($value): string
    {
        $name = $this->default;

        return $this->$name->encode($value);
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    public function decode(string $value)
    {
        $name = $this->default;

        return $this->$name->decode($value);
    }
}
