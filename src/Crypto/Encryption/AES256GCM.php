<?php

namespace Hail\Crypto\Encryption;

use Hail\Crypto\Exception\CryptoException;

/**
 * Class AES256GCM
 *
 * @package Hail\Crypto
 */
class AES256GCM
{
    private const CIPHER_METHOD = 'aes-256-gcm';
    private const TAG_BYTE_SIZE = 16;
    private const BLOCK_BYTE_SIZE = 12;
    private const AD_STRING = 'hail.crypto';

    private $sodium = false;

    public function __construct()
    {
        if (
            \function_exists('\\sodium_crypto_aead_aes256gcm_is_available') &&
            \sodium_crypto_aead_aes256gcm_is_available()
        ) {
            $this->sodium = true;
        }
    }

    public function ivSize(): int
    {
        return static::BLOCK_BYTE_SIZE;
    }

    public function encrypt(
        string $plaintext,
        string $key,
        string $ad = null
    ): string {
        $iv = \random_bytes(static::BLOCK_BYTE_SIZE);
        $ad = $this->getAd($ad);

        if ($this->sodium) {
            $cipherText = \sodium_crypto_aead_aes256gcm_encrypt($plaintext, $ad, $iv, $key);
        } else {
            $cipherText = \openssl_encrypt(
                $plaintext,
                static::CIPHER_METHOD,
                $key,
                \OPENSSL_RAW_DATA,
                $iv, $tag, $ad
            );
            $cipherText .= $tag;
        }

        if ($cipherText === false) {
            throw new CryptoException('Encrypt failed.');
        }

        return $iv . $cipherText;
    }

    public function decrypt(
        string $cipherText,
        string $key,
        string $ad = null
    ): string {
        $iv = \mb_substr($cipherText, 0, static::BLOCK_BYTE_SIZE);
        $cipherText = \mb_substr($cipherText, static::BLOCK_BYTE_SIZE);
        if ($iv === false || $cipherText === false) {
            throw new CryptoException('Environment is broken');
        }

        $ad = $this->getAd($ad);

        if ($this->sodium) {
            $plaintext = \sodium_crypto_aead_aes256gcm_decrypt($cipherText, $ad, $iv, $key);
        } else {
            $tag = \mb_substr($cipherText, -static::TAG_BYTE_SIZE, null, '8bit');
            $cipherText = \mb_substr($cipherText, 0, -static::TAG_BYTE_SIZE, '8bit');
            if ($tag === false || $cipherText === false) {
                throw new CryptoException('Environment is broken');
            }

            $plaintext = \openssl_decrypt(
                $cipherText,
                static::CIPHER_METHOD,
                $key,
                \OPENSSL_RAW_DATA,
                $iv, $tag, $ad
            );
        }

        if ($plaintext === false) {
            throw new CryptoException('Decrypt failed.');
        }

        return $plaintext;
    }


    private function getAd(string $ad = null): string
    {
        if ($ad !== '' && $ad !== null) {
            return static::AD_STRING . '.' . $ad;
        }

        return static::AD_STRING;
    }
}