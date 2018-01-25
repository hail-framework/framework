<?php

namespace Hail\Jose;

use Hail\Jose\Signature\{
    EdDSA, NONE, HMAC, RSA, ECDSA, PSS
};

final class Signer
{
    public const NONE = 'none',
        EdDSA = 'EdDSA',
        HS256 = 'HS256',
        HS384 = 'HS384',
        HS512 = 'HS512',
        RS256 = 'RS256',
        RS384 = 'RS384',
        RS512 = 'RS512',
        ES256 = 'ES256',
        ES384 = 'ES384',
        ES512 = 'ES512',
        PS256 = 'PS256',
        PS384 = 'PS384',
        PS512 = 'PS512';


    public const ALL = [
        self::NONE, self::EdDSA,
        self::HS256, self::HS384, self::HS512,
        self::RS256, self::RS384, self::RS512,
        self::ES256, self::ES384, self::ES512,
        self::PS256, self::PS384, self::PS512,
    ];

    private const METHOD = [
        self::NONE => NONE::class,
        self::EdDSA => EdDSA::class,
        self::HS256 => HMAC::class,
        self::HS384 => HMAC::class,
        self::HS512 => HMAC::class,
        self::RS256 => RSA::class,
        self::RS384 => RSA::class,
        self::RS512 => RSA::class,
        self::ES256 => ECDSA::class,
        self::ES384 => ECDSA::class,
        self::ES512 => ECDSA::class,
        self::PS256 => PSS::class,
        self::PS384 => PSS::class,
        self::PS512 => PSS::class,
    ];

    private const HASH = [
        self::HS256 => 'sha256',
        self::HS384 => 'sha384',
        self::HS512 => 'sha512',
        self::RS256 => 'sha256',
        self::RS384 => 'sha384',
        self::RS512 => 'sha512',
        self::ES256 => 'sha256',
        self::ES384 => 'sha384',
        self::ES512 => 'sha512',
        self::PS256 => 'sha256',
        self::PS384 => 'sha384',
        self::PS512 => 'sha512',
    ];

    /**
     * @var string
     */
    private $algorithm;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string|null
     */
    private $hash;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $passphrase = '';

    public function __construct(string $algorithm, ?string $key, string $passphrase = '')
    {
        $this->algorithm = self::supported($algorithm);

        $this->method = self::METHOD[$algorithm];
        $this->hash = self::HASH[$algorithm] ?? null;

        if ($key !== null) {
            $this->setKey($key, $passphrase);
        }
    }

    public function setKey(string $key, string $passphrase = '')
    {
        $this->key = $key;
        $this->passphrase = $passphrase;
    }

    private function getKey($type)
    {
        if ($this->method === HMAC::class) {
            return \base64_decode($this->key);
        }

        if (\in_array($this->method, [RSA::class, ECDSA::class, PSS::class, EdDSA::class], true)) {
            if ($type === 'sign') {
                return $this->method::getPrivateKey($this->key, $this->passphrase);
            }

            if ($type === 'verify') {
                return $this->method::getPublicKey($this->key);
            }
        }

        \trigger_error('JWT use `none` algorithm is not safe', E_USER_WARNING);

        return null;
    }

    public static function supported($algorithm)
    {
        if (!\in_array($algorithm, self::ALL, true)) {
            throw new \RangeException('Algorithm not supported');
        }

        return $algorithm;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function sign(string $payload): string
    {
        return $this->method::sign($payload, $this->getKey(__FUNCTION__), $this->hash);
    }

    public function verify(string $expected, string $payload): bool
    {
        return $this->method::verify($expected, $payload, $this->getKey(__FUNCTION__), $this->hash);
    }
}