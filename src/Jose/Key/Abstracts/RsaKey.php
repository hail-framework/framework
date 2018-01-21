<?php

namespace Hail\Jose\Key\Abstracts;


abstract class RsaKey extends OpenSSLKey
{
    protected function getOpensslKeyType()
    {
        return \OPENSSL_KEYTYPE_RSA;
    }

    protected function getOpensslKeyName()
    {
        return 'rsa';
    }

    protected function getJWKMap()
    {
        return [
            'n' => 'n',
            'd' => 'd',
            'e' => 'e',
            'p' => 'p',
            'q' => 'q',
            'dmp1' => 'dp',
            'dmq1' => 'dq',
            'iqmp' => 'qi',
        ];
    }
}