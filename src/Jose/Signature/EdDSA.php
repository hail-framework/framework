<?php

namespace Hail\Jose\Signature;

use Hail\Jose\Util\Base64Url;


/**
 * Class EdDSA
 * only for crv Ed25519
 *
 * @package Hail\Jose\Signature
 */
class EdDSA
{
    public function sign(string $payload, $key): string
    {
        return \sodium_crypto_sign_detached($payload, $key);
    }

    public static function verify(string $signature, string $payload, $key): bool
    {
        return \sodium_crypto_sign_verify_detached($signature, $payload, $key);
    }

    public static function getPrivateKey(string $content)
    {
        $keyPair = \sodium_crypto_sign_seed_keypair($content);

        return \sodium_crypto_sign_secretkey($keyPair);
    }

    public static function getPublicKey(string $content)
    {
        $keyPair = \sodium_crypto_sign_seed_keypair($content);

        return \sodium_crypto_sign_publickey($keyPair);
    }

    public static function getJWK($key): array
    {
        $publicKey = self::getPublicKey($key);

        return [
            'kty' => 'OKP',
            'crv' => 'Ed25519',
            'd' => Base64Url::encode($key),
            'x' => Base64Url::encode($publicKey),
        ];
    }
}