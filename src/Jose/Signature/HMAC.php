<?php

namespace Hail\JWT\Signature;

use Hail\JWT\Util\Base64Url;

final class HMAC
{
    public static function sign(string $payload, string $key, string $hash): string
    {
        return \hash_hmac($hash, $payload, $key, true);
    }

    public static function verify(string $signature, string $payload, string $key, string $hash): bool
    {
        return \hash_equals($signature, self::sign($payload, $key, $hash));
    }

    public static function getJWK(string $key): array
    {
        return [
            'kty' => 'oct',
            'k' => Base64Url::encode($key),
        ];
    }
}