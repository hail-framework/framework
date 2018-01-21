<?php

namespace Hail\Jose\Signer;


use Hail\Jose\Key\KeyInterface;

final class None implements SignerInterface
{
    public function getAlgorithm(): string
    {
        return 'none';
    }

    public function sign(string $payload, KeyInterface $key): string
    {
        return '';
    }

    public function verify(string $expected, string $payload, KeyInterface $key): bool
    {
        return $expected === '';
    }
}