<?php

namespace Hail\Serialize\Adapter;

use Hail\Serialize\AdapterInterface;

class Serialize implements AdapterInterface
{
    public static function getInstance(): AdapterInterface
    {
        return new static();
    }

    public function encode($value): string
    {
        return \serialize($value);
    }

    public function decode(string $value)
    {
        return \unserialize($value, ['allowed_classes' => false]);
    }
}