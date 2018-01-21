<?php

namespace Hail\Jose\Signer\Abstracts;

use Hail\Jose\Key\KeyInterface;
use Hail\Jose\Key\RsaPrivateKey;
use Hail\Jose\Key\RsaPublicKey;

abstract class Rsa extends Signer
{
    public function sign(string $payload, KeyInterface $key): string
    {
        if (!$key instanceof RsaPrivateKey) {
            throw new \InvalidArgumentException('This key is not compatible with RSA signatures');
        }

        $signature = '';
        if (!\openssl_sign($payload, $signature, $key->get(), $this->method)) {
            throw new \DomainException(
                'There was an error while creating the signature: ' . \openssl_error_string()
            );
        }

         return $signature;
    }

    public function verify(string $expected, string $payload, KeyInterface $key): bool
    {
        if (!$key instanceof RsaPublicKey) {
            throw new \InvalidArgumentException('This key is not compatible with RSA signatures');
        }

        switch (\openssl_verify($payload, $expected, $key->get(), $this->method)) {
            case 1:
                return true;

            case 0:
                return false;

            default:
                // returns 1 on success, 0 on failure, -1 on error.
                throw new \DomainException('OpenSSL error: ' . \openssl_error_string());
        }
    }
}