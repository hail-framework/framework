<?php

namespace Hail\Jose\Encryption;

use Hail\Jose\Encryption\Abstracts\AESCBCHS;

final class A128CBCHS256 extends AESCBCHS
{
    public function getKeySize(): int
    {
        return 32; // 32 * 8 = 256
    }

    public function getAlgorithmName()
    {
        return 'A128CBC-HS256';
    }
}