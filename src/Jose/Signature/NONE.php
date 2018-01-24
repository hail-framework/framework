<?php

namespace Hail\Jose\Signature;

final class NONE
{
    public static function sign(): string
    {
        return '';
    }

    public static function verify(string $hash, string $expected): bool
    {
        return $expected === '';
    }
}