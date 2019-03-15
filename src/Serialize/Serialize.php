<?php

namespace Hail\Serialize;

use Hail\Util\SingletonTrait;

class Serialize
{
    use SingletonTrait;

    public function encode($value): string
    {
        return \serialize($value);
    }

    public function decode(string $value)
    {
        return \unserialize($value, ['allowed_classes' => false]);
    }
}