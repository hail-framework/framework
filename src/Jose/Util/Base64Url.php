<?php

namespace Hail\Jose\Util;


class Base64Url
{
    /**
     * @param string $data       The data to encode
     * @param bool   $usePadding If true, the "=" padding at end of the encoded value are kept, else it is removed
     *
     * @return string The data encoded
     */
    public static function encode(string $data, bool $usePadding = false): string
    {
        $encoded = \strtr(\base64_encode($data), '+/', '-_');

        return $usePadding ? $encoded : \rtrim($encoded, '=');
    }

    /**
     * @param string $data The data to decode
     *
     * @return string The data decoded
     */
    public static function decode(string $data): string
    {
        return \base64_decode(\strtr($data, '-_', '+/'));
    }
}