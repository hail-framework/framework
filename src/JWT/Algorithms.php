<?php

namespace Hail\JWT;


interface Algorithms
{
    public const HS256 = 'HS256';
    public const HS384 = 'HS384';
    public const HS512 = 'HS512';

    public const RS256 = 'RS256';
    public const RS384 = 'RS384';
    public const RS512 = 'RS512';

    public const ALL = [
        self::HS256 => ['hmac', 'SHA256'],
        self::HS384 => ['hmac', 'SHA384'],
        self::HS512 => ['hmac', 'SHA512'],
        self::RS256 => ['rsa', 'SHA256'],
        self::RS384 => ['rsa', 'SHA384'],
        self::RS512 => ['rsa', 'SHA512'],
    ];
}