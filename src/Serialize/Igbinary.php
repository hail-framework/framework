<?php

namespace Hail\Serialize;

\defined('IGBINARY_EXTENSION') || \define('IGBINARY_EXTENSION', \extension_loaded('igbinary'));

class Igbinary
{
    public function __construct()
    {
        if (!IGBINARY_EXTENSION) {
            throw new \LogicException('Igbinary extension not loaded');
        }
    }

    public function encode($value): string
    {
        return \igbinary_serialize($value);
    }

    public function decode(string $value)
    {
        return \igbinary_unserialize($value);
    }
}