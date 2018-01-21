<?php

namespace Hail\Jose\Encryption;


interface EncryptionInterface
{
    /**
     * Encrypt data.
     *
     * @param string      $data   The data to encrypt
     * @param string      $cek    The content encryption key
     * @param string      $iv     The Initialization Vector
     * @param string|null $aad    Additional Additional Authenticated Data
     * @param string      $header The Protected Header encoded in Base64Url
     * @param string      $tag    Tag
     *
     * @return string The encrypted data
     */
    public function encrypt(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $header,
        string &$tag
    ): string;

    /**
     * Decrypt data.
     *
     * @param string      $data   The data to decrypt
     * @param string      $cek    The content encryption key
     * @param string      $iv     The Initialization Vector
     * @param string|null $aad    Additional Additional Authenticated Data
     * @param string      $header The Protected Header encoded in Base64Url
     * @param string      $tag    Tag
     *
     * @return string
     */
    public function decrypt(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $header,
        string $tag
    ): string;

    /**
     * @return int|null
     */
    public function getIVSize(): ?int;

    /**
     * @return int
     */
    public function getCEKSize(): int;
}