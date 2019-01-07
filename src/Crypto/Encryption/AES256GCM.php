<?php

namespace Hail\Crypto\Encryption;


use Hail\Crypto\Raw;
use Hail\Crypto\Exception\CryptoException;

/**
 * Class AES256GCM
 *
 * @package Hail\Crypto
 */
class AES256GCM extends AbstractAES
{
    //VERSION (4 bytes) || SALT (32 bytes) || IV (12 bytes) || CIPHERTEXT (varies) || HMAC (32 bytes)
    private const MINIMUM_CIPHERTEXT_SIZE = 80;

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

    /**
     * Encrypts a string with a Key.
     *
     * @param string $plaintext
     * @param string $key
     * @param string $ad
     * @param bool   $password
     *
     * @throws CryptoException
     *
     * @return string
     * @throws \Exception
     */
    public function encrypt(
        string $plaintext,
        string $key,
        string $ad = '',
        bool $password = false
    ): string {
        if (\mb_strlen($key, '8bit') !== static::KEY_BYTE_SIZE) {
            throw new CryptoException('Bad key length.');
        }

        $salt = \random_bytes(static::SALT_BYTE_SIZE);

        [$authKey, $encryptKey] = $this->deriveKeys($key, $salt, $password);

        $iv = \random_bytes(static::BLOCK_BYTE_SIZE);

        $ad = $this->getAd($ad);

        if ($this->sodium) {
            $cipherText = \sodium_crypto_aead_aes256gcm_encrypt($plaintext, $ad, $iv, $encryptKey);
        } else {
            $cipherText = \openssl_encrypt(
                $plaintext,
                static::CIPHER_METHOD,
                $encryptKey,
                \OPENSSL_RAW_DATA,
                $iv, $tag, $ad
            );
            $cipherText .= $tag;
        }

        if ($cipherText === false) {
            throw new CryptoException('Encrypt failed.');
        }

        $cipherText = static::CURRENT_VERSION . $salt . $iv . $cipherText;
        $auth = \hash_hmac(static::HASH_TYPE, $cipherText, $authKey, true);
        $cipherText .= $auth;

        return new Raw($cipherText);
    }

    /**
     * Encrypts a string with a password, using a slow key derivation function
     * to make password cracking more expensive.
     *
     * @param string $plaintext
     * @param string $password
     * @param string $ad
     *
     * @throws \Exception
     *
     * @return string
     */
    public function encryptWithPassword(
        string $plaintext,
        string $password,
        string $ad = ''
    ): string {
        return $this->encrypt($plaintext, $password, $ad, true);
    }

    /**
     * Decrypts a cipher text to a string with a Key.
     *
     * @param string $cipherText
     * @param string $key
     * @param string $ad
     * @param bool   $password
     *
     * @throws CryptoException
     *
     * @return string
     */
    public function decrypt(
        string $cipherText,
        string $key,
        string $ad = '',
        bool $password = false
    ): string {
        if (\mb_strlen($cipherText, '8bit') < static::MINIMUM_CIPHERTEXT_SIZE) {
            throw new CryptoException('Ciphertext is too short.');
        }

        // Get and check the version header.
        $header = \mb_substr($cipherText, 0, static::HEADER_VERSION_SIZE, '8bit');
        if ($header !== static::CURRENT_VERSION) {
            throw new CryptoException('Bad version header.');
        }

        // Get the salt.
        $salt = \mb_substr(
            $cipherText,
            static::HEADER_VERSION_SIZE,
            static::SALT_BYTE_SIZE,
            '8bit'
        );
        if ($salt === false) {
            throw new CryptoException('Environment is broken');
        }

        // Get the IV.
        $iv = \mb_substr(
            $cipherText,
            static::HEADER_VERSION_SIZE + static::SALT_BYTE_SIZE,
            static::BLOCK_BYTE_SIZE,
            '8bit'
        );
        if ($iv === false) {
            throw new CryptoException('Environment is broken');
        }

        // Get the HMAC.
        $hmac = \mb_substr(
            $cipherText,
            \mb_strlen($cipherText, '8bit') - static::MAC_BYTE_SIZE,
            static::MAC_BYTE_SIZE,
            '8bit'
        );
        if ($hmac === false) {
            throw new CryptoException('Environment is broken');
        }

        // Get the actual encrypted ciphertext.
        $encrypted = \mb_substr(
            $cipherText,
            static::HEADER_VERSION_SIZE + static::SALT_BYTE_SIZE +
            static::BLOCK_BYTE_SIZE,
            \mb_strlen($cipherText, '8bit') - static::MAC_BYTE_SIZE - static::SALT_BYTE_SIZE -
            static::BLOCK_BYTE_SIZE - static::HEADER_VERSION_SIZE,
            '8bit'
        );
        if ($encrypted === false) {
            throw new CryptoException('Environment is broken');
        }
        // Derive the separate encryption and authentication keys from the key
        // or password, whichever it is.
        [$authKey, $encryptKey] = $this->deriveKeys($key, $salt, $password);

        if (false === \hash_equals($hmac,
                \hash_hmac(static::HASH_TYPE, $header . $salt . $iv . $encrypted, $authKey, true))
        ) {
            throw new CryptoException('Integrity check failed.');
        }

        $ad = $this->getAd($ad);

        if ($this->sodium) {
            $plaintext = \sodium_crypto_aead_aes256gcm_decrypt($encrypted, $ad, $iv, $encryptKey);
        } else {
            $tag = \mb_substr($encrypted, -static::TAG_BYTE_SIZE, null, '8bit');
            $encrypted = \mb_substr($encrypted, 0, -static::TAG_BYTE_SIZE, '8bit');

            $plaintext = \openssl_decrypt(
                $encrypted,
                static::CIPHER_METHOD,
                $encryptKey,
                \OPENSSL_RAW_DATA,
                $iv, $tag, $ad
            );
        }

        if ($plaintext === false) {
            throw new CryptoException('Decrypt failed.');
        }

        return $plaintext;
    }

    /**
     * Decrypts a ciphertext to a string with a password, using a slow key
     * derivation function to make password cracking more expensive.
     *
     * @param string $cipherText
     * @param string $password
     * @param string $ad
     *
     * @throws CryptoException
     *
     * @return string
     */
    public function decryptWithPassword(
        string $cipherText,
        string $password,
        string $ad = ''
    ): string {
        return $this->decrypt($cipherText, $password, $ad, true);
    }

    private function getAd(string $ad = ''): string
    {
        if ($ad !== '') {
            return static::AD_STRING . '.' . $ad;
        }

        return static::AD_STRING;
    }
}