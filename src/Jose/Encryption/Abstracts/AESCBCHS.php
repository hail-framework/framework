<?php

namespace Hail\Jose\Encryption\Abstracts;


use Hail\Jose\Encryption\EncryptionInterface;

abstract class AESCBCHS implements EncryptionInterface
{
    public function encrypt(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $header,
        string &$tag
    ): string {
        $k = \mb_substr($cek, \mb_strlen($cek, '8bit') / 2, null, '8bit');

        $cypherText = \openssl_encrypt($data, $this->getMode($k), $k, \OPENSSL_RAW_DATA, $iv);

        $tag = $this->authenticationTag($cypherText, $cek, $iv, $aad, $header);

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
        if ($tag !== $this->authenticationTag($data, $cek, $iv, $aad, $header)) {
            throw new \UnexpectedValueException('Unable to verify the tag.');
        }

        $k = \mb_substr($cek, \mb_strlen($cek, '8bit') / 2, null, '8bit');

        return \openssl_decrypt($data, $this->getMode($k), $k, OPENSSL_RAW_DATA, $iv);
    }

    protected function authenticationTag($data, $cek, $iv, $aad, $header)
    {
        $calculatedAad = $header;
        if (null !== $aad) {
            $calculatedAad .= '.' . $aad;
        }
        $macKey = \mb_substr($cek, 0, \mb_strlen($cek, '8bit') / 2, '8bit');
        $authDataLength = \mb_strlen($header, '8bit');

        $secured_input = \implode('', [
            $calculatedAad,
            $iv,
            $data,
            \pack('N2',
                ($authDataLength / 2147483647) * 8,
                ($authDataLength % 2147483647) * 8
            ), // str_pad(dechex($auth_data_length), 4, "0", STR_PAD_LEFT)
        ]);
        $hash = \hash_hmac($this->getHashAlgorithm(), $secured_input, $macKey, true);

        return \mb_substr($hash, 0, \mb_strlen($hash, '8bit') / 2, '8bit');
    }

    /**
     * @return string
     */
    protected function getHashAlgorithm()
    {
        static $hash;

        if ($hash === null) {
            $hash = 'sha' . \substr(static::class,
                    \strrpos(static::class, 'HS') + 2
                );
        }

        return $hash;
    }

    /**
     * @return int
     */
    public function getIVSize(): int
    {
        return 128;
    }

    /**
     * @param string $k
     *
     * @return string
     */
    private function getMode($k): string
    {
        return 'aes-' . (8 * \mb_strlen($k, '8bit')) . '-cbc';
    }
}