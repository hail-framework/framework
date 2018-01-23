<?php

namespace Hail\Jose\Encryption;


use Hail\Jose\Encryption\Abstracts\AESGCM;

final class A192GCM extends AESGCM
{
    public function getKeySize(): int
    {
        return 24; // 24 * 8 = 192
    }

    public function getAlgorithmName()
    {
        return 'A192GCM';
    }
}