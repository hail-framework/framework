<?php

namespace Hail\Jose\Key\Abstracts;


abstract class EcdsaKey extends OpenSSLKey
{
    /**
     * @var int
     */
    protected $length;

    public function getLength()
    {
        if ($this->length === null) {
            if ($this->details === null) {
                $this->get();
            }

            $this->length = \ceil($this->details['bits'] / 8) * 2;
        }

        return $this->length;
    }

    protected function getOpensslKeyType()
    {
        return \OPENSSL_KEYTYPE_EC;
    }

    protected function getOpensslKeyName()
    {
        return 'ec';
    }

    protected function getJWKMap()
    {
        return [
            'x' => 'x',
            'y' => 'y',
            'd' => 'd',
        ];
    }
}