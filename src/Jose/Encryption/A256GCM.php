<?php

namespace Hail\Jose\Encryption;


use Hail\Jose\Encryption\Abstracts\AESGCM;

final class A256GCM extends AESGCM
{
    public function getKeySize(): int
    {
        return 32; // 32 * 8 = 256
    }

    public function getAlgorithmName()
    {
        return 'A256GCM';
    }
}