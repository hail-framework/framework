<?php

namespace Hail\Crypto\Encryption;

use Hail\Crypto\Exception\CryptoException;

class AES256CTR
{
    private const CIPHER_METHOD = 'aes-256-ctr';
    private const BLOCK_BYTE_SIZE = 16;

    public function ivSize(): int
    {
        return static::BLOCK_BYTE_SIZE;
    }

    public function encrypt(
        string $plaintext,
        string $key
    ): string {
        $iv = \random_bytes(static::BLOCK_BYTE_SIZE);

        $cipherText = \openssl_encrypt(
            $plaintext,
            static::CIPHER_METHOD,
            $key,
            \OPENSSL_RAW_DATA,
            $iv
        );

        if ($cipherText === false) {
            throw new CryptoException('Encrypt failed.');
        }

        return $iv . $cipherText;
    }

    public function decrypt(
        string $cipherText,
        string $key
    ): string {
        $iv = \mb_substr($cipherText, 0, static::BLOCK_BYTE_SIZE);
        $cipherText = \mb_substr($cipherText, static::BLOCK_BYTE_SIZE);
        if ($iv === false || $cipherText === false) {
            throw new CryptoException('Environment is broken');
        }

        $plaintext = \openssl_decrypt(
            $cipherText,
            static::CIPHER_METHOD,
            $key,
            \OPENSSL_RAW_DATA,
            $iv
        );

        if ($plaintext === false) {
            throw new CryptoException('Decrypt failed.');
        }

        return $plaintext;
    }
}