<?php

namespace Hail\Jose\Encryption;


use Hail\Jose\Encryption\Abstracts\AESCBCHS;

final class A256CBCHS512 extends AESCBCHS
{
    public function getCEKSize(): int
    {
        return 512;
    }

    public function getAlgorithmName()
    {
        return 'A256CBC-HS512';
    }
}