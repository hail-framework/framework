<?php

namespace Hail\Jose\Encryption;


use Hail\Jose\Encryption\Abstracts\AESGCM;

final class A256GCM extends AESGCM
{
    public function getCEKSize(): int
    {
        return 256;
    }

    public function getAlgorithmName()
    {
        return 'A256GCM';
    }
}