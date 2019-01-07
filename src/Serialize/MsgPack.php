<?php

namespace Hail\Serialize;

\defined('MSGPACK_EXTENSION') || \define('MSGPACK_EXTENSION', \extension_loaded('msgpack'));

class MsgPack
{
    public function __construct()
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