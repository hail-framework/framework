<?php

namespace Hail\Crypto\Encryption;

use Hail\Crypto\Raw;
use Hail\Crypto\Exception\CryptoException;

class AES256CTR extends AbstractAES
{
    //VERSION (4 bytes) || SALT (32 bytes) || IV (16 bytes) || CIPHERTEXT (varies) || HMAC (32 bytes)
    private const MINIMUM_CIPHERTEXT_SIZE = 84;

    private const CIPHER_METHOD = 'aes-256-ctr';
    private const BLOCK_BYTE_SIZE = 16;

    /**
     * Encrypts a string with a Key.
     *
     * @param string $plaintext
     * @param string $key
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
        bool $password = false
    ): string {
        if (\mb_strlen($key, '8bit') !== static::KEY_BYTE_SIZE) {
            throw new CryptoException('Bad key length.');
        }

        $salt = \random_bytes(static::SALT_BYTE_SIZE);

        [$authKey, $encryptKey] = $this->deriveKeys($key, $salt, $password);

        $iv = \random_bytes(static::BLOCK_BYTE_SIZE);


        $cipherText = \openssl_encrypt(
            $plaintext,
            static::CIPHER_METHOD,
            $encryptKey,
            \OPENSSL_RAW_DATA,
            $iv
        );

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
     *
     * @throws \Exception
     *
     * @return string
     */
    public function encryptWithPassword(
        string $plaintext,
        string $password
    ): string {
        return $this->encrypt($plaintext, $password, true);
    }

    /**
     * Decrypts a cipher text to a string with a Key.
     *
     * @param string $cipherText
     * @param string $key
     * @param bool   $password
     *
     * @throws CryptoException
     *
     * @return string
     */
    public function decrypt(
        string $cipherText,
        string $key,
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


        $plaintext = \openssl_decrypt(
            $encrypted,
            static::CIPHER_METHOD,
            $encryptKey,
            \OPENSSL_RAW_DATA,
            $iv
        );

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
     *
     * @throws CryptoException
     *
     * @return string
     */
    public function decryptWithPassword(
        string $cipherText,
        string $password
    ): string {
        return $this->decrypt($cipherText, $password, true);
    }
}