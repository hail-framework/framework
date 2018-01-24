<?php

namespace Hail\Jose\Signature;



use Hail\Jose\Helpers;

final class HMAC
{
    public static function sign(string $hash, string $payload, string $key): string
    {
        return \hash_hmac($hash, $payload, $key, true);
    }

    public static function verify(string $hash, string $expected, string $payload, string $key): bool
    {
        return \hash_equals($expected, self::sign($hash, $payload, $key));
    }

    public static function getJWK(string $key): array
    {
        return [
            'kty' => 'oct',
            'k' => Helpers::base64UrlEncode($key),
        ];
    }
}