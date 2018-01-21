<?php

namespace Hail\Jose\Encryption;


use Hail\Jose\Encryption\Abstracts\AESCBCHS;

final class A192CBCHS384 extends AESCBCHS
{
    public function getCEKSize(): int
    {
        return 384;
    }

    public function getAlgorithmName()
    {
        return 'A192CBC-HS384';
    }
}