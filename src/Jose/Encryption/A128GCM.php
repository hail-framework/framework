<?php

namespace Hail\Jose\Encryption;


use Hail\Jose\Encryption\Abstracts\AESGCM;

final class A128GCM extends AESGCM
{
    public function getKeySize(): int
    {
        return 16; // 16 *8 = 128
    }

    public function getAlgorithmName()
    {
        return 'A128GCM';
    }
}