<?php

namespace Hail\Jose\Key;


interface KeyInterface
{
    public function get();

    public function toJWK(): array;
}