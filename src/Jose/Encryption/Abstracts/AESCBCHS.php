<?php

namespace Hail\Jose\Encryption\Abstracts;


use Hail\Jose\Encryption\EncryptionInterface;

abstract class AESCBCHS implements EncryptionInterface
{
    public function encrypt(
        string $data,
        string $cek,
        ?string $aad,
        string $header
    ): array {
        $cekLen = \mb_strlen($cek, '8bit');
        if ($cekLen !== $this->getKeySize()) {
            throw new \UnexpectedValueException('Bad key encryption key length.');
        }

        if ($cekLen % 2 !== 0) {
            throw new \UnexpectedValueException('AES-CBC with HMAC encryption expected key of even number size');
        }

        $len = $cekLen / 2;
        $aesKey = \mb_substr($cek, $len, null, '8bit');
        $mode = 'aes-' . ($cekLen * 4) . '-cbc';
        $iv = \random_bytes(16);

        $cypherText = \openssl_encrypt($data, $mode, $aesKey, \OPENSSL_RAW_DATA, $iv);

        $hmacKey = \mb_substr($cek, 0, $len, '8bit');
        $tag = $this->computeAuthTag($cypherText, $hmacKey, $iv, $aad, $header);

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
        $cekLen = \mb_strlen($cek, '8bit');
        if ($cekLen !== $this->getKeySize()) {
            throw new \UnexpectedValueException('Bad key encryption key length.');
        }

        if ($cekLen % 2 !== 0) {
            throw new \UnexpectedValueException('AES-CBC with HMAC encryption expected key of even number size');
        }

        $len = $cekLen / 2;
        $hmacKey = \mb_substr($cek, 0, $len, '8bit');

        if ($tag !== $this->computeAuthTag($data, $hmacKey, $iv, $aad, $header)) {
            throw new \UnexpectedValueException('Unable to verify the tag.');
        }

        $aesKey = \mb_substr($cek, $len, null, '8bit');
        $mode = 'aes-' . ($cekLen * 4) . '-cbc';

        return \openssl_decrypt($data, $mode, $aesKey, \OPENSSL_RAW_DATA, $iv);
    }

    protected function computeAuthTag($data, $hmacKey, $iv, $aad, $header)
    {
        $calculatedAad = $header;
        if (null !== $aad) {
            $calculatedAad .= '.' . $aad;
        }

        $aadLen = \mb_strlen($calculatedAad, '8bit');
        $max32bit = 2147483647;

        $hmacInput = \implode('', [
            $calculatedAad,
            $iv,
            $data,
            \pack('N2', ($aadLen / $max32bit) * 8, ($aadLen % $max32bit) * 8),
        ]);
        $hash = \hash_hmac('sha' . ($this->getKeySize() * 8) , $hmacInput, $hmacKey, true);

        return \mb_substr($hash, 0, \mb_strlen($hash, '8bit') / 2, '8bit');
    }
}