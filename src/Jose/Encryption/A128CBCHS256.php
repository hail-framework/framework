<?php

namespace Hail\Jose\Encryption;

use Hail\Jose\Encryption\Abstracts\AESCBCHS;

final class A128CBCHS256 extends AESCBCHS
{
    public function getCEKSize(): int
    {
        return 256;
    }

    public function getAlgorithmName()
    {
        return 'A128CBC-HS256';
    }
}