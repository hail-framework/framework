<?php

namespace Hail\Serialize;

use Hail\Util\SingletonTrait;

\defined('IGBINARY_EXTENSION') || \define('IGBINARY_EXTENSION', \extension_loaded('igbinary'));

class Igbinary
{
    use SingletonTrait;

    protected function init()
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