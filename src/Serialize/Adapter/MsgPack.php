<?php

namespace Hail\Serialize\Adapter;

use Hail\Serialize\AdapterInterface;

\defined('MSGPACK_EXTENSION') || \define('MSGPACK_EXTENSION', \extension_loaded('msgpack'));

class MsgPack implements AdapterInterface
{
    public static function getInstance(): AdapterInterface
    {
        if (!MSGPACK_EXTENSION) {
            throw new \LogicException('MsgPack extension not loaded');
        }

        return new static();
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