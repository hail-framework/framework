<?php

namespace Hail\Jose\Encryption;


interface EncryptionInterface
{
    /**
     * Encrypt data.
     *
     * @param string      $data   The data to encrypt
     * @param string      $cek    The content encryption key
     * @param string|null $aad    Additional Additional Authenticated Data
     * @param string      $header The Protected Header encoded in Base64Url
     *
     * @return array [:iv, :cypherText, :tag]
     */
    public function encrypt(
        string $data,
        string $cek,
        ?string $aad,
        string $header
    ): array;

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
     * @return int
     */
    public function getKeySize(): int;
}