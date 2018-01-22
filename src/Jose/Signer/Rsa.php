<?php

namespace Hail\Jose\Signer;

class Rsa
{
    protected const KEY_TYPE = \OPENSSL_KEYTYPE_RSA;

    public static function sign(string $hash, string $payload, resource $key): string
    {
         $signature = '';
        if (!\openssl_sign($payload, $signature, $key, $hash)) {
            throw new \DomainException(
                'There was an error while creating the signature: ' . \openssl_error_string()
            );
        }

         return $signature;
    }

    public static function verify(string $hash, string $expected, string $payload, resource $key): bool
    {
        switch (\openssl_verify($payload, $expected, $key, $hash)) {
            case 1:
                return true;

            case 0:
                return false;

            default:
                // returns 1 on success, 0 on failure, -1 on error.
                throw new \DomainException('OpenSSL error: ' . \openssl_error_string());
        }
    }

    public static function getPrivateKey(string $content, string $passphrase): resource
    {
        $key = \openssl_pkey_get_private($content, $passphrase);

        return self::validateKey($key);
    }

    public static function getPublicKey(string $content): resource
    {
        $key = \openssl_pkey_get_public($content);

        return self::validateKey($key);
    }

    protected static function validateKey(resource $key)
    {
        if ($key === false) {
            throw new \InvalidArgumentException(
                'It was not possible to parse your key, reason: ' . \openssl_error_string()
            );
        }

        $details = \openssl_pkey_get_details($key);

        if (!isset($details['key']) || $details['type'] !== static::KEY_TYPE) {
            throw new \InvalidArgumentException('This key is not compatible with RSA');
        }

        return $key;
    }
}