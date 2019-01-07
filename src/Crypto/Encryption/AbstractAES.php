<?php

namespace Hail\Crypto\Encryption;


use Hail\Crypto\Exception\CryptoException;

/**
 * Class AbstractAES
 *
 * @package Hail\Crypto
 */
class AbstractAES
{
    protected const HEADER_VERSION_SIZE = 4;
    protected const CURRENT_VERSION = "\xDE\xF5\x02\x00";

    protected const SALT_BYTE_SIZE = 32;
    protected const MAC_BYTE_SIZE = 32;
    protected const KEY_BYTE_SIZE = 32;
    protected const HASH_TYPE = 'sha256';
    protected const ENCRYPTION_INFO_STRING = 'Hail|V1|KeyForEncryption';
    protected const AUTHENTICATION_INFO_STRING = 'Hail|V1|KeyForAuthentication';

    protected const PBKDF2_ITERATIONS = 100000;

    /**
     * @var array
     */
    public static $hashList = [
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

    /**
     * Derives authentication and encryption keys from the secret, using a slow
     * key derivation function if the secret is a password.
     *
     * @param string $key
     * @param string $salt
     * @param bool   $password
     *
     * @throws CryptoException
     *
     * @return array
     */
    protected function deriveKeys(string $key, string $salt, bool $password = false): array
    {
        if ($password) {
            $preHash = \hash(static::HASH_TYPE, $key, true);
            $key = $this->pbkdf2(
                static::HASH_TYPE,
                $preHash,
                $salt,
                static::PBKDF2_ITERATIONS,
                static::KEY_BYTE_SIZE,
                true
            );
        }

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
        if (!isset(static::$hashList[$algorithm])) {
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