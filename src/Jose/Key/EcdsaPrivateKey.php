<?php

namespace Hail\Jose\Key;

use Hail\Jose\Key\Abstracts\EcdsaKey;
use Hail\Jose\Key\Traits\PrivateKeyTrait;

class EcdsaPrivateKey extends EcdsaKey
{
    use PrivateKeyTrait;
}