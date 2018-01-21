<?php

namespace Hail\Jose\Signer\Abstracts;


use Hail\Jose\Signer\SignerInterface;

abstract class Signer implements SignerInterface
{
    protected $algorithm;

    protected $method;

    public function __construct()
    {
        $class = static::class;

        $this->algorithm = \substr($class, \strrpos($class, '\\') + 1);
        $this->method = 'sha' . \substr($this->algorithm, 2);
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }
}