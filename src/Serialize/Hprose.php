<?php

namespace Hail\Serialize;

\defined('HPROSE_EXTENSION') || \define('HPROSE_EXTENSION', \extension_loaded('hprose'));

class Hprose
{
    public function __construct()
    {
        if (!HPROSE_EXTENSION) {
            throw new \LogicException('Hprose extension not loaded');
        }
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