<?php

namespace Hail\Crypto;

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
 * @property-read AES256CTR $aes256ctr
 * @property-read AES256GCM $aes256gcm
 */
class Crypto
{
    private const MAP = [
        'password' => Password::class,
        'hash' => Hash::class,
        'hmac' => Hmac::class,
        'rsa' => RSA::class,
        'aes256ctr' => AES256CTR::class,
        'aes256gcm' => AES256GCM::class,
    ];

    private $default;

    public function __construct(array $config)
    {
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
        string $key
    ): Raw {
        $name = $this->default;

        return $this->$name->encrypt($plaintext, $key);
    }

    public function encryptWithPassword(
        string $plaintext,
        string $password
    ): Raw {
        $name = $this->default;

        return $this->$name->encryptWithPassword($plaintext, $password);
    }

    public function decrypt(
        string $cipherText,
        string $key
    ): string {
        $name = $this->default;

        return $this->$name->decrypt($cipherText, $key);
    }

    public function decryptWithPassword(
        string $cipherText,
        string $password
    ): string {
        $name = $this->default;

        return $this->$name->decryptWithPassword($cipherText, $password);
    }

    public function raw(string $text, string $format = null): Raw
    {
        return new Raw($text, $format);
    }
}