<?php

namespace Hail\Jose\Encryption\Abstracts;


use Hail\Jose\Encryption\EncryptionInterface;

abstract class AESGCM implements EncryptionInterface
{
    public function encrypt(
        string $data,
        string $cek,
        ?string $aad,
        string $header
    ): array {
        $calculatedAad = $header;
        if (null !== $aad) {
            $calculatedAad .= '.' . $aad;
        }

        $cekLen = \mb_strlen($cek, '8bit');
        if ($cekLen !== $this->getKeySize()) {
            throw new \UnexpectedValueException('Bad key encryption key length.');
        }

        $mode = 'aes-' . ($cekLen * 8) . '-gcm';
        $iv = \random_bytes(12);
        $tag = null;

        $cypherText = \openssl_encrypt($data, $mode, $cek, OPENSSL_RAW_DATA, $iv, $tag, $calculatedAad);

        if ($cypherText === false) {
            throw new \UnexpectedValueException('Unable to encrypt the data.');
        }

        return [$iv, $cypherText, $tag];
    }

    public function decrypt(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $header,
        string $tag
    ): string {
        $calculatedAad = $header;
        if (null !== $aad) {
            $calculatedAad .= '.' . $aad;
        }

        $cekLen = \mb_strlen($cek, '8bit');
        if ($cekLen !== $this->getKeySize()) {
            throw new \UnexpectedValueException('Bad key encryption key length.');
        }

        $mode = 'aes-' . ($cekLen * 8) . '-gcm';
        $raw = \openssl_decrypt($data, $mode, $cek, OPENSSL_RAW_DATA, $iv, $tag, $calculatedAad);
        if ($raw === false) {
            throw new \UnexpectedValueException('Unable to decrypt or to verify the tag.');
        }

        return $raw;
    }
}