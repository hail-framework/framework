<?php

namespace Hail\Crypto;

use Hail\Crypto\Exception\CryptoException;
use Hail\Crypto\Hash\{
    Password, Hash, Hmac
};
use Hail\Crypto\Encryption\{
    RSA, AES256CTR, AES256GCM
};

/**
 * Class Crypto
 *
 * @package Hail\Crypto
 * @property-read Password  $password
 * @property-read Hash      $hash
 * @property-read Hmac      $hamc
 * @property-read Rsa       $rsa
 */
class Crypto
{
    private const MAP = [
        'password' => Password::class,
        'hash' => Hash::class,
        'hmac' => Hmac::class,
        'rsa' => RSA::class,
    ];

    private const ENCRYPTION = [
        'aes256ctr' => AES256CTR::class,
        'aes256gcm' => AES256GCM::class,
    ];

    protected const HEADER_VERSION_SIZE = 4;
    protected const CURRENT_VERSION = "\xDE\xF5\x02\x00";

    protected const SALT_BYTE_SIZE = 32;
    protected const MAC_BYTE_SIZE = 32;
    protected const KEY_BYTE_SIZE = 32;
    protected const HASH_TYPE = 'sha256';
    protected const ENCRYPTION_INFO_STRING = 'Hail|V1|KeyForEncryption';
    protected const AUTHENTICATION_INFO_STRING = 'Hail|V1|KeyForAuthentication';

    protected const PBKDF2_ITERATIONS = 100000;

    public const HASH = [
        // 'md5' => 16,
        'sha1' => 20,
        'sha224' => 28,
        'sha256' => 32,
        'sha384' => 48,
        'sha512' => 64,
        // 'ripemd128' => 16,
        'ripemd160' => 20,
        'ripemd256' => 32,
        'ripemd320' => 40,
        'whirlpool' => 64,
    ];

    private $default;

    public function __construct(array $config)
    {
        if (!isset(static::ENCRYPTION[$config['default']])) {
            throw new \InvalidArgumentException('Encryption not defined: ' . $config['default']);
        }

        $this->default = $config['default'];
    }

    public function __get($name)
    {
        if (!isset(static::MAP[$name])) {
            throw new \InvalidArgumentException('Property not defined: ' . $name);
        }

        return $this->$name = new (static::MAP[$name])();
    }

    public function __call($name, $arguments)
    {
        if (isset(static::MAP[$name])) {
            return $this->$name;
        }

        throw new \BadMethodCallException('Method not defined: ' . $name);
    }

    public function encrypt(
        string $plaintext,
        string $key,
        string $ad = null
    ): Raw {
        if (\mb_strlen($key, '8bit') !== static::KEY_BYTE_SIZE) {
            throw new CryptoException('Bad key length.');
        }

        $salt = \random_bytes(static::SALT_BYTE_SIZE);

        return $this->encryptInternal($plaintext, $key, $salt, $ad);
    }

    public function encryptWithPassword(
        string $plaintext,
        string $password,
        string $ad = null
    ): Raw {
        $salt = \random_bytes(static::SALT_BYTE_SIZE);
        $key = $this->passwordKey($password, $salt);

        return $this->encryptInternal($plaintext, $key, $salt, $ad);
    }

    private function encryptInternal(string $plaintext, string $key, string $salt, string $ad = null): Raw
    {
        [$authKey, $encryptKey] = $this->deriveKeys($key, $salt);

        $object = $this->{$this->default};

        $cipherText = $object->encrypt($plaintext, $encryptKey, $ad);

        $cipherText = static::CURRENT_VERSION . $salt . $cipherText;
        $cipherText .= \hash_hmac(static::HASH_TYPE, $cipherText, $authKey, true);

        return new Raw($cipherText);
    }

    public function decrypt(
        string $cipherText,
        string $key,
        string $ad = null
    ): string {
        [$header, $salt, $encrypted, $hmac] = $this->decryptSplit($cipherText);

        [$authKey, $encryptKey] = $this->deriveKeys($key, $salt);

        if (false === \hash_equals($hmac,
                \hash_hmac(static::HASH_TYPE, $header . $salt . $encrypted, $authKey, true))
        ) {
            throw new CryptoException('Integrity check failed.');
        }

        return $this->{$this->default}->decrypt($encrypted, $encryptKey, $ad);
    }

    public function decryptWithPassword(
        string $cipherText,
        string $password,
        string $ad = null
    ): string {
        [$header, $salt,, $encrypted, $hmac] = $this->decryptSplit($cipherText);

        $key = $this->passwordKey($password, $salt);
        [$authKey, $encryptKey] = $this->deriveKeys($key, $salt);

        if (false === \hash_equals($hmac,
                \hash_hmac(static::HASH_TYPE, $header . $salt . $encrypted, $authKey, true))
        ) {
            throw new CryptoException('Integrity check failed.');
        }

        return $this->{$this->default}->decrypt($encrypted, $encryptKey, $ad);
    }

    private function decryptSplit(string $cipherText): array
    {
        $object = $this->{$this->default};

        $size = \mb_strlen($cipherText, '8bit');
        // VERSION (4 bytes) || SALT (32 bytes) || IV (? bytes) || CIPHERTEXT (varies) || HMAC (32 bytes)
        if ($size < 68 + $object->ivSize()) {
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

        // Get the HMAC.
        $hmac = \mb_substr(
            $cipherText,
            $size - static::MAC_BYTE_SIZE,
            static::MAC_BYTE_SIZE,
            '8bit'
        );
        if ($hmac === false) {
            throw new CryptoException('Environment is broken');
        }

        $encrypted = \mb_substr(
            $cipherText,
            static::HEADER_VERSION_SIZE + static::SALT_BYTE_SIZE,
            -static::MAC_BYTE_SIZE,
            '8bit'
        );

        if ($encrypted === false) {
            throw new CryptoException('Environment is broken');
        }

        return [$header, $salt, $encrypted, $hmac];
    }

    public function raw(string $text, string $format = null): Raw
    {
        return new Raw($text, $format);
    }

    /**
     * Derives authentication and encryption keys from the secret
     *
     * @param string $key
     * @param string $salt
     *
     * @throws CryptoException
     *
     * @return array
     */
    private function deriveKeys(string $key, string $salt): array
    {
        $authKey = \hash_hkdf(
            static::HASH_TYPE,
            $key,
            static::KEY_BYTE_SIZE,
            static::AUTHENTICATION_INFO_STRING,
            $salt
        );

        $encryptKey = \hash_hkdf(
            static::HASH_TYPE,
            $key,
            static::KEY_BYTE_SIZE,
            static::ENCRYPTION_INFO_STRING,
            $salt
        );

        return [$authKey, $encryptKey];
    }

    private function passwordKey(string $key, string $salt): string
    {
        $preHash = \hash(static::HASH_TYPE, $key, true);

        return $this->pbkdf2(
            static::HASH_TYPE,
            $preHash,
            $salt,
            static::PBKDF2_ITERATIONS,
            static::KEY_BYTE_SIZE,
            true
        );
    }

    /**
     * Computes the PBKDF2 password-based key derivation function.
     *
     * The PBKDF2 function is defined in RFC 2898. Test vectors can be found in
     * RFC 6070. This implementation of PBKDF2 was originally created by Taylor
     * Hornby, with improvements from http://www.variations-of-shadow.com/.
     *
     * @param string $algorithm The hash algorithm to use. Recommended: SHA256
     * @param string $password  The password.
     * @param string $salt      A salt that is unique to the password.
     * @param int    $count     Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * @param int    $length    The length of the derived key in bytes.
     * @param bool   $raw       If true, the key is returned in raw binary format. Hex encoded otherwise.
     *
     * @throws CryptoException
     *
     * @return string A $key_length-byte key derived from the password and salt.
     */
    public function pbkdf2(
        string $algorithm,
        string $password,
        string $salt,
        int $count,
        int $length,
        bool $raw = false
    ): string {
        $algorithm = \strtolower($algorithm);
        // Whitelist, or we could end up with people using CRC32.
        if (!isset(static::HASH[$algorithm])) {
            throw new CryptoException('Algorithm is not a secure cryptographic hash function.');
        }

        if ($count <= 0 || $length <= 0) {
            throw new CryptoException('Invalid PBKDF2 parameters.');
        }

        // The output length is in NIBBLES (4-bits) if $raw_output is false!
        if (!$raw) {
            $length *= 2;
        }

        return \hash_pbkdf2($algorithm, $password, $salt, $count, $length, $raw);
    }
}