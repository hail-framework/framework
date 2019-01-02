<?php

namespace Hail\Serialize;


interface AdapterInterface
{
    public static function getInstance(): AdapterInterface;

    public function encode($value): string;

    public function decode(string $value);
}