<?php

namespace Hail\Jose\Encryption;


use Hail\Jose\Encryption\Abstracts\AESCBCHS;

final class A192CBCHS384 extends AESCBCHS
{
    public function getKeySize(): int
    {
        return 48; // 48 * 8 = 384
    }

    public function getAlgorithmName()
    {
        return 'A192CBC-HS384';
    }
}