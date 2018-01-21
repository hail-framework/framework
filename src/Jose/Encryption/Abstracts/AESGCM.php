<?php

namespace Hail\Jose\Encryption\Abstracts;


use Hail\Jose\Encryption\EncryptionInterface;

abstract class AESGCM implements EncryptionInterface
{
    public function encrypt(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $header,
        string &$tag
    ): string {
        $calculatedAad = $header;
        if (null !== $aad) {
            $calculatedAad .= '.' . $aad;
        }

        $keyLength = \mb_strlen($cek, '8bit') * 8;
        if ($keyLength !== $this->getCEKSize()) {
            throw new \RangeException('Bad key encryption key length.');
        }

        $mode = 'aes-' . $keyLength . '-gcm';

        $cypherText = \openssl_encrypt($data, $mode, $cek, OPENSSL_RAW_DATA, $iv, $tag, $calculatedAad);

        if ($cypherText === false) {
            throw new \UnexpectedValueException('Unable to encrypt the data.');
        }

        return $cypherText;
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

        $keyLength = \mb_strlen($cek, '8bit') * 8;
        if ($keyLength !== $this->getCEKSize()) {
            throw new \RangeException('Bad key encryption key length.');
        }

        $tag_length = \mb_strlen($tag, '8bit') * 8;
        if (!\in_array($tag_length, [128, 120, 112, 104, 96], true)) {
            throw new \RangeException('Invalid tag length. Supported values are: 128, 120, 112, 104 and 96.');
        }

        $mode = 'aes-' . $keyLength . '-gcm';
        $raw = \openssl_decrypt($data, $mode, $cek, OPENSSL_RAW_DATA, $iv, $tag, $calculatedAad);
        if ($raw === false) {
            throw new \UnexpectedValueException('Unable to decrypt or to verify the tag.');
        }

        return $raw;
    }

    /**
     * @return int
     */
    public function getIVSize(): int
    {
        return 96;
    }
}