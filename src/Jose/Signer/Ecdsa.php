<?php

namespace Hail\Jose\Signer;

use FG\ASN1\Universal\Integer;
use FG\ASN1\Universal\Sequence;
use FG\ASN1\ASNObject;

final class Ecdsa extends Rsa
{
    protected const KEY_TYPE = \OPENSSL_KEYTYPE_EC;

    private const HASH_LENGTH_MAP = [
        'sha256' => 64,
        'sha384' => 96,
        'sha512' => 132,
    ];

    public static function sign(string $hash, string $payload, resource $key): string
    {
        $signature = parent::sign($hash, $payload, $key);

        /* @var \FG\ASN1\Universal\Sequence $asn */
        $asn = ASNObject::fromBinary($signature);

        $length = self::getLength($key, $hash);

        $signature = '';
        foreach ($asn->getChildren() as $child) {
            /* @var \FG\ASN1\Universal\Integer $child */
            $content = $child->getContent();
            $content = \gmp_strval(\gmp_init($content), 16);
            $content = \str_pad($content, $length, '0', STR_PAD_LEFT);
            $content = \hex2bin($content);

            $signature .= $content;
        }

        return $signature;
    }

    public static function verify(string $hash, string $expected, string $payload, resource $key): bool
    {
        $signature = \bin2hex($expected);
        $length = self::getLength($key, $hash);

        $R = \mb_substr($signature, 0, $length, '8bit');
        $S = \mb_substr($signature, $length, null, '8bit');

        $sequence = new Sequence(
            new Integer(\gmp_strval(\gmp_init($R, 16), 10)),
            new Integer(\gmp_strval(\gmp_init($S, 16), 10))
        );

        return parent::verify($hash, $sequence->getBinary(), $payload, $key);
    }

    private static function getLength(resource $key, string $hash = null): int
    {
        if ($hash !== null && isset(self::HASH_LENGTH_MAP[$hash])) {
            return self::HASH_LENGTH_MAP[$hash];
        }

        $details = \openssl_pkey_get_details($key);

        return \ceil($details['bits'] / 8) * 2;
    }
}