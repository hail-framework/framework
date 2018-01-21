<?php

namespace Hail\Jose\Key\Traits;


trait PublicKeyTrait
{
    protected function getOpensslKey()
    {
        return \openssl_pkey_get_public($this->content);
    }
}