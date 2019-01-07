<?php

namespace Hail\Crypto\Encryption;


use Hail\Crypto\Exception\CryptoException;
use Hail\Crypto\Raw;

class RSA
{
    /**
     * @param string $data
     * @param string $publicKey
     *
     * @return string
     * @throws CryptoException
     */
    public function encrypt(string $data, string $publicKey): string
    {
        $key = \openssl_pkey_get_public($publicKey);

        $encrypted = '';
        if (!\openssl_public_encrypt($data, $encrypted, $key, \OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new CryptoException('RSA public encrypt error: ' . \openssl_error_string());
        }
        \openssl_pkey_free($key);

        return new Raw($encrypted);
    }

    /**
     * @param string $data
     * @param string $privateKey
     *
     * @return string
     * @throws CryptoException
     */
    public function decrypt(string $data, string $privateKey): string
    {
        $key = \openssl_pkey_get_private($privateKey);

        $decrypted = '';
        if (!\openssl_private_decrypt($data, $decrypted, $key, \OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new CryptoException('RSA private decrypt error: ' . \openssl_error_string());
        }
        \openssl_pkey_free($key);

        return $decrypted;
    }

    /**
     * @param string $data
     * @param string $privateKey
     *
     * @return string
     * @throws CryptoException
     */
    public function signature(string $data, string $privateKey): string
    {
        $key = \openssl_pkey_get_private($privateKey);
        if (!\openssl_sign($data, $signature, $key, \OPENSSL_ALGO_SHA256)) {
            throw new CryptoException('RSA signature error: ' . \openssl_error_string());
        }

        return new Raw($signature);
    }

    /**
     * @param string $data
     * @param string $signature
     * @param string $publicKey
     *
     * @return bool
     * @throws CryptoException
     */
    public static function verify(string $data, string $signature, string $publicKey): bool
    {
        $key = \openssl_pkey_get_public($publicKey);

        $return = \openssl_verify($data, $signature, $key, \OPENSSL_ALGO_SHA256);
        if ($return === -1) {
            throw new CryptoException('RSA signature check error: ' . \openssl_error_string());
        }

        return $return === 1;
    }
}