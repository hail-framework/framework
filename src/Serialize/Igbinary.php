<?php

namespace Hail\Serialize;

\defined('IGBINARY_EXTENSION') || \define('IGBINARY_EXTENSION', \extension_loaded('igbinary'));

class Igbinary implements AdapterInterface
{
    public static function getInstance(): AdapterInterface
    {
        if (!IGBINARY_EXTENSION) {
            throw new \LogicException('Igbinary extension not loaded');
        }

        return new static();
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