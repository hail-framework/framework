<?php

namespace Hail\Jose\Encryption;


use Hail\Jose\Encryption\Abstracts\AESGCM;

final class A192GCM extends AESGCM
{
    public function getCEKSize(): int
    {
        return 192;
    }

    public function getAlgorithmName()
    {
        return 'A192GCM';
    }
}