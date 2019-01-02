<?php

namespace Hail\Serialize;

use Hail\Serialize\Adapter\{Hprose, Igbinary, Json, MsgPack, Serialize as PhpSerialize, Yaml};

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
 * @property-read MsgPack      $msgpak
 * @property-read Igbinary     $igbinary
 * @property-read Hprose       $hprose
 * @property-read Json         $json
 * @property-read PhpSerialize $php
 * @property-read Yaml         $yaml
 * @method MsgPack msgpak()
 * @method Igbinary igbinary()
 * @method Hprose hprose()
 * @method Json json()
 * @method PhpSerialize php()
 * @method Yaml yaml()
 */
final class Serializer
{
    public const ADAPTERS = [
        'msgpack' => MsgPack::class,
        'igbinary' => Igbinary::class,
        'hprose' => Hprose::class,
        'json' => Json::class,
        'php' => PhpSerialize::class,
        'yaml' => Yaml::class,
    ];

    /**
     * @var AdapterInterface
     */
    private $default;


    public function __construct(?string $type)
    {
        $type = $type ?? \env('SERIALIZE_TYPE') ?? 'php';

        $this->default = $this->$type;
    }

    public function __get($name)
    {
        if (!isset(self::ADAPTERS[$name])) {
            throw new \InvalidArgumentException('Serialize type not defined: ' . $type);
        }

        $object = new (self::ADAPTERS[$name])();
        if ($object === null) {
            throw new \LogicException('Extension not loaded for ' . $name);
        }

        return $this->$name = $object;
    }

    public function __call($name, $arguments)
    {
        return $this->$name;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public function encode($value): string
    {
        return $this->default->encode($value);
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    public function decode(string $value)
    {
        return $this->default->decode($value);
    }
}
