<?php

namespace Hail\Serialize;

\defined('HPROSE_EXTENSION') || \define('HPROSE_EXTENSION', \extension_loaded('hprose'));

class Hprose implements AdapterInterface
{
    public static function getInstance(): AdapterInterface
    {
        if (!HPROSE_EXTENSION) {
            throw new \LogicException('Hprose extension not loaded');
        }

        return new static();
    }

    public function encode($value): string
    {
        return \hprose_serialize($value);
    }

    public function decode(string $value)
    {
        return \hprose_unserialize($value);
    }
}