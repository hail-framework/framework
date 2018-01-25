<?php

namespace Hail\JWT\Signature;

use Hail\JWT\Util\RSA as JoseRSA;

class PSS extends RSA
{
    public static function sign(string $payload, $key, string $hash): string
    {
        if (!\is_resource($key)) {
            throw new \InvalidArgumentException('Key is not a openssl key resource');
        }

        [$parts, $modulusLen] = JoseRSA::getKeyDetails($key);
        $em = self::encodeEMSAPSS($payload, 8 * $modulusLen - 1, $hash);
        $message = JoseRSA::gmpFromBin($em);
        $signature = JoseRSA::exponentiate($parts, $message);

        return JoseRSA::convertIntegerToOctetString($signature, $modulusLen);
    }

    private static function encodeEMSAPSS(string $message, int $modulusLength, string $hash): string
    {
        $emLen = ($modulusLength + 1) >> 3;
        $sLen = JoseRSA::getHashLength($hash);
        $mHash = \hash($hash, $message, true);
        if ($emLen <= 2 * $sLen + 2) {
            throw new \InvalidArgumentException('Invalid length');
        }
        $salt = \random_bytes($sLen);
        $m2 = "\0\0\0\0\0\0\0\0" . $mHash . $salt;
        $h = \hash($hash, $m2, true);
        $ps = \str_repeat(\chr(0), $emLen - $sLen * 2 - 2);
        $db = $ps . \chr(1) . $salt;
        $dbMask = JoseRSA::getMGF1($h, $emLen - $sLen - 1, $hash);
        $maskedDB = $db ^ $dbMask;
        $maskedDB[0] = ~\chr(0xFF << ($modulusLength & 7)) & $maskedDB[0];

        return $maskedDB . $h . \chr(0xBC);
    }

    public static function verify(string $signature, string $payload, $key, string $hash): bool
    {
        if (!\is_resource($key)) {
            throw new \InvalidArgumentException('Key is not a openssl key resource');
        }

        [$parts, $modulusLen] = JoseRSA::getKeyDetails($key);

        if (\mb_strlen($signature, '8bit') !== $modulusLen) {
            throw new \InvalidArgumentException('Expected length error');
        }

        $s2 = JoseRSA::gmpFromBin($signature);
        $m2 = JoseRSA::exponentiate($parts, $s2);
        $em = JoseRSA::convertIntegerToOctetString($m2, $modulusLen);
        $modBits = 8 * $modulusLen;

        return self::verifyEMSAPSS($payload, $em, $modBits - 1, $hash);
    }

    private static function verifyEMSAPSS(string $m, string $em, int $emBits, string $hash): bool
    {
        $emLen = ($emBits + 1) >> 3;
        $sLen = JoseRSA::getHashLength($hash);
        $mHash = hash($hash, $m, true);
        if ($emLen < $sLen * 2 + 2) {
            throw new \InvalidArgumentException('Invalid EMBits');
        }
        if ($em[\mb_strlen($em, '8bit') - 1] !== \chr(0xBC)) {
            throw new \InvalidArgumentException('Invalid EM');
        }

        $maskedDB = \mb_substr($em, 0, -$sLen - 1, '8bit');
        $h = \mb_substr($em, -$sLen - 1, $sLen, '8bit');
        $temp = \chr(0xFF << ($emBits & 7));
        if ((~$maskedDB[0] & $temp) !== $temp) {
            throw new \InvalidArgumentException();
        }
        $dbMask = JoseRSA::getMGF1($h, $emLen - $sLen - 1, $hash);
        $db = $maskedDB ^ $dbMask;
        $db[0] = ~\chr(0xFF << ($emBits & 7)) & $db[0];
        $temp = $emLen - $sLen - $sLen - 2;
        if (\mb_strpos($db, \str_repeat(\chr(0), $temp), 0, '8bit') !== 0) {
            throw new \InvalidArgumentException();
        }
        if (1 !== \ord($db[$temp])) {
            throw new \InvalidArgumentException();
        }
        $salt = \mb_substr($db, $temp + 1, null, '8bit'); // should be $sLen long
        $m2 = "\0\0\0\0\0\0\0\0".$mHash.$salt;
        $h2 = \hash($hash, $m2, true);

        return \hash_equals($h, $h2);
    }
}