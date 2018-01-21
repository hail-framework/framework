<?php

namespace Hail\Jose\Key;


use Hail\Jose\Key\Abstracts\RsaKey;
use Hail\Jose\Key\Traits\PrivateKeyTrait;

class RsaPrivateKey extends RsaKey
{
    use PrivateKeyTrait;
}