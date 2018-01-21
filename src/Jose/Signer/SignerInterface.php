<?php

namespace Hail\Jose\Signer;


use Hail\Jose\Key\KeyInterface;

interface SignerInterface
{
    /**
     * Returns the algorithm id
     *
     * @return string
     */
    public function getAlgorithm(): string;

    /**
     * Creates a hash for the given payload
     *
     * @param string $payload
     * @param KeyInterface $key
     *
     * @return string
     */
    public function sign(string $payload, KeyInterface $key): string;

    /**
     * Returns if the expected hash matches with the data and key
     *
     * @param string $expected
     * @param string $payload
     * @param KeyInterface $key
     *
     * @return bool
     */
    public function verify(string $expected, string $payload, KeyInterface $key): bool;
}