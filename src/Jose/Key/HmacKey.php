<?php

namespace Hail\Jose\Key;


class HmacKey implements KeyInterface
{
    private $key;

    public function __construct(string $content)
    {
        $this->key = $content;
    }

    public function get()
    {
        return $this->key;
    }

    public function toJWK(): array
    {
        return [
            'kty' => 'oct',
            'k' => $this->key,
        ];
    }
}