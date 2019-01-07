<?php

namespace Hail\Serialize;

class Serialize
{
    public function encode($value): string
    {
        return \serialize($value);
    }

    public function decode(string $value)
    {
        return \unserialize($value, ['allowed_classes' => false]);
    }
}