<?php

namespace Hail\Jose\Signer\Abstracts;


use Hail\Jose\Key\HmacKey;
use Hail\Jose\Key\KeyInterface;

abstract class Hmac extends Signer
{
    public function sign(string $payload, KeyInterface $key): string
    {
        if (!$key instanceof HmacKey) {
            throw new \InvalidArgumentException('This key is not compatible with HMAC signatures');
        }

        return \hash_hmac($this->method, $payload, $key->get(), true);
    }

    public function verify(string $expected, string $payload, KeyInterface $key): bool
    {
        if (!$key instanceof HmacKey) {
            throw new \InvalidArgumentException('This key is not compatible with HMAC signatures');
        }

        return \hash_equals($expected, $this->sign($payload, $key));
    }
}