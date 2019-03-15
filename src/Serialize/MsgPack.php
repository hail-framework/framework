<?php

namespace Hail\Serialize;

use Hail\Util\SingletonTrait;

\defined('MSGPACK_EXTENSION') || \define('MSGPACK_EXTENSION', \extension_loaded('msgpack'));

class MsgPack
{
    use SingletonTrait;

    protected function init()
    {
        if (!MSGPACK_EXTENSION) {
            throw new \LogicException('MsgPack extension not loaded');
        }
    }

    public function encode($value): string
    {
        return \msgpack_pack($value);
    }

    public function decode(string $value)
    {
        return \msgpack_unpack($value);
    }
}