<?php

namespace Hail\Jose\Signature;

use Hail\Jose\Helpers;

class RSA
{
    protected const KEY_TYPE = \OPENSSL_KEYTYPE_RSA;

    protected const JOSE_KTY = 'rsa';
    protected const JOSE_MAP = [
        'n' => 'n',
        'd' => 'd',
        'e' => 'e',
        'p' => 'p',
        'q' => 'q',
        'dmp1' => 'dp',
        'dmq1' => 'dq',
        'iqmp' => 'qi',
    ];

    /**
     * @param string   $hash
     * @param string   $payload
     * @param resource $key
     *
     * @return string
     */
    public static function sign(string $hash, string $payload, $key): string
    {
        if (!\is_resource($key)) {
            throw new \InvalidArgumentException('Key is not a openssl key resource');
        }

        $signature = '';
        if (!\openssl_sign($payload, $signature, $key, $hash)) {
            throw new \DomainException(
                'There was an error while creating the signature: ' . \openssl_error_string()
            );
        }

        return $signature;
    }

    /**
     * @param string   $hash
     * @param string   $expected
     * @param string   $payload
     * @param resource $key
     *
     * @return bool
     */
    public static function verify(string $hash, string $expected, string $payload, $key): bool
    {
        if (!\is_resource($key)) {
            throw new \InvalidArgumentException('Key is not a openssl key resource');
        }

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
        static::validateKey($key);

        return $key;
    }

    public static function getPublicKey(string $content): resource
    {
        $key = \openssl_pkey_get_public($content);
        static::validateKey($key);

        return $key;
    }

    protected static function validateKey($key)
    {
        if (!\is_resource($key)) {
            throw new \InvalidArgumentException(
                'It was not possible to parse your key, reason: ' . \openssl_error_string()
            );
        }

        $details = \openssl_pkey_get_details($key);

        if (!isset($details['key']) || $details['type'] !== static::KEY_TYPE) {
            throw new \InvalidArgumentException('This key is not compatible with RSA');
        }

        return $details;
    }

    public static function getJWK($key): array
    {
        $details = static::validateKey($key);

        $jwk = [
            'kty' => \strtoupper(static::JOSE_KTY),
        ];

        foreach ($details[static::JOSE_KTY] as $k => $v) {
            if (isset(static::JOSE_MAP[$k])) {
                $jwk[static::JOSE_MAP[$k]] = Helpers::base64UrlEncode($v);
            }
        }

        return $jwk;
    }
}