<?php

namespace Hail\Jose\Signature;



final class Hmac
{
    public static function sign(string $hash, string $payload, string $key): string
    {
        return \hash_hmac($hash, $payload, $key, true);
    }

    public static function verify(string $hash, string $expected, string $payload, string $key): bool
    {
        return \hash_equals($expected, self::sign($hash, $payload, $key));
    }
}