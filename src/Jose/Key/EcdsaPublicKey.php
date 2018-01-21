<?php

namespace Hail\Jose\Key;

use Hail\Jose\Key\Abstracts\EcdsaKey;
use Hail\Jose\Key\Traits\PublicKeyTrait;

class EcdsaPublicKey extends EcdsaKey
{
    use PublicKeyTrait;
}