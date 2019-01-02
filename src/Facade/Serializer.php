<?php

namespace Hail\Facade;

use Hail\Serialize\Adapter\{
    Hprose,
    Igbinary,
    Json,
    MsgPack,
    Serialize as PhpSerialize
};

/**
 * Class Serialize
 *
 * @package Hail\Facade
 *
 * @method static string encode(mixed $value)
 * @method static mixed decode(string $value)
 * @method static MsgPack msgpak()
 * @method static Igbinary igbinary()
 * @method static Hprose hprose()
 * @method static Json json()
 * @method static PhpSerialize php()
 */
class Serializer extends Facade
{
}