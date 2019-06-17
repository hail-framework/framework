<?php

namespace Hail\Jose\Signature;

final class ECDSA extends RSA
{
    protected const KEY_TYPE = \OPENSSL_KEYTYPE_EC;

    protected const JOSE_KTY = 'ec';
    protected const JOSE_MAP = [
        'x' => 'x',
        'y' => 'y',
        'd' => 'd',
        'curve_oid' => 'crv',
    ];

    protected const HASH_LENGTH = [
        'sha256' => 64,
        'sha384' => 96,
        'sha512' => 132,
    ];

    protected const CURVE = [
        '1.2.840.10045.3.1.7' => 'P-256',
        '1.3.132.0.34' => 'P-384',
        '1.3.132.0.35' => 'P-521',
    ];

    public static function sign(string $payload, $key, string $hash): string
    {
        return self::fromDER(
            parent::sign($payload, $key, $hash),
            self::getHashLength($hash)
        );
    }

    public static function fromDER(string $der, int $partLength): string
    {
        $hex = unpack('H*', $der)[1];
        if (0 !== mb_strpos($hex, '30', 0, '8bit')) { // SEQUENCE
            throw new \InvalidArgumentException('Invalid ASN.1 SEQUENCE');
        }

        if ('81' === \mb_substr($hex, 2, 2, '8bit')) { // LENGTH > 128
            $hex = \mb_substr($hex, 6, null, '8bit');
        } else {
            $hex = \mb_substr($hex, 4, null, '8bit');
        }

        if (0 !== \mb_strpos($hex, '02', 0, '8bit')) { // INTEGER
            throw new \RuntimeException('Invalid ASN.1 INTEGER');
        }

        $Rl = \hexdec(\mb_substr($hex, 2, 2, '8bit'));
        $R = self::retrievePositiveInteger(\mb_substr($hex, 4, $Rl * 2, '8bit'));
        $R = \str_pad($R, $partLength, '0', STR_PAD_LEFT);

        $hex = \mb_substr($hex, 4 + $Rl * 2, null, '8bit');
        if (0 !== \mb_strpos($hex, '02', 0, '8bit')) { // INTEGER
            throw new \RuntimeException('Invalid ASN.1 INTEGER');
        }

        $Sl = \hexdec(\mb_substr($hex, 2, 2, '8bit'));
        $S = self::retrievePositiveInteger(\mb_substr($hex, 4, $Sl * 2, '8bit'));
        $S = \str_pad($S, $partLength, '0', STR_PAD_LEFT);

        return \pack('H*', $R . $S);
    }

    /**
     * @param string $data
     *
     * @return string
     */
    private static function retrievePositiveInteger(string $data): string
    {
        while (0 === \mb_strpos($data, '00', 0, '8bit') && \mb_substr($data, 2, 2, '8bit') > '7f') {
            $data = \mb_substr($data, 2, null, '8bit');
        }

        return $data;
    }

    public static function verify(string $signature, string $payload, $key, string $hash): bool
    {
        return parent::verify(
            self::toDER($signature, self::getHashLength($hash)),
            $payload, $key, $hash
        );
    }

    public static function toDER(string $signature, int $partLength): string
    {
        $signature = \unpack('H*', $signature)[1];
        if (\mb_strlen($signature, '8bit') !== 2 * $partLength) {
            throw new \InvalidArgumentException('Invalid length.');
        }
        $R = \mb_substr($signature, 0, $partLength, '8bit');
        $S = \mb_substr($signature, $partLength, null, '8bit');

        $R = self::preparePositiveInteger($R);
        $Rl = \mb_strlen($R, '8bit') / 2;
        $S = self::preparePositiveInteger($S);
        $Sl = \mb_strlen($S, '8bit') / 2;
        $der = \pack('H*',
            '30' . ($Rl + $Sl + 4 > 128 ? '81' : '') . \dechex($Rl + $Sl + 4)
            . '02' . \dechex($Rl) . $R
            . '02' . \dechex($Sl) . $S
        );

        return $der;
    }

    private static function preparePositiveInteger(string $data): string
    {
        if (\mb_substr($data, 0, 2, '8bit') > '7f') {
            return '00' . $data;
        }

        while (0 === \mb_strpos($data, '00', 0, '8bit') && \mb_substr($data, 2, 2, '8bit') <= '7f') {
            $data = \mb_substr($data, 2, null, '8bit');
        }

        return $data;
    }

    public static function getJWK($key): array
    {
        $jwk = parent::getJWK($key);
        $jwk['crv'] = self::getECKeyCurve($jwk['crv']);

        return $jwk;
    }

    private static function getHashLength(string $hash): int
    {
        if (!isset(self::HASH_LENGTH[$hash])) {
            throw new \InvalidArgumentException('Unsupported Hash.');
        }

        return self::HASH_LENGTH[$hash];
    }

    private static function getECKeyCurve(string $oid): string
    {
        if (!isset(self::CURVE[$oid])) {
            throw new \InvalidArgumentException('Unsupported OID.');
        }

        return self::CURVE[$oid];
    }
}
