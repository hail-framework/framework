<?php

namespace Hail\Jose\Util;


class RSA
{
    public const HASH_LENGTH = [
        'sha256' => 32,
        'sha384' => 48,
        'sha512' => 64,
    ];

    public static function getMGF1($mgfSeed, $maskLen, $hash)
    {
        $t = '';
        $count = \ceil($maskLen / self::HASH_LENGTH[$hash]);
        for ($i = 0; $i < $count; ++$i) {
            $c = \pack('N', $i);
            $t .= \hash($hash, $mgfSeed . $c, true);
        }

        return \mb_substr($t, 0, $maskLen, '8bit');
    }

    public static function getHashLength($hash)
    {
        if (!isset(self::HASH_LENGTH[$hash])) {
            throw new \InvalidArgumentException("Hash `$hash` not supoort");
        }

        return self::HASH_LENGTH[$hash];
    }

    public static function gmpFromBin(string $value): \GMP
    {
        $value = '0x' . \unpack('H*', $value)[1];

        return \gmp_init($value, 16);
    }

    /**
     * Exponentiate with or without Chinese Remainder Theorem.
     * Operation with primes 'p' and 'q' is appox. 2x faster.
     *
     * @param array $key
     * @param \GMP  $c
     *
     * @return \GMP
     */
    public static function exponentiate(array $key, \GMP $c): \GMP
    {
        if (\gmp_cmp($c, $key['n']) > 0 || \gmp_cmp($c, \gmp_init(0)) < 0) {
            throw new \RuntimeException('RSA key invalid');
        }

        if (!isset($key['d'], $key['p'], $key['q'], $key['dmp1'], $key['dmq1'], $key['iqmp'])) {
            return \gmp_powm($c, $key['e'], $key['n']);
        }

        [
            'p' => $p,
            'q' => $q,
            'dmp1' => $dP,
            'dmq1' => $dQ,
            'iqmp' => $qInv,
        ] = $key;

        $m1 = \gmp_powm($c, $dP, $p);
        $m2 = \gmp_powm($c, $dQ, $q);
        $h = \gmp_mod(\gmp_mul($qInv, \gmp_add(\gmp_sub($m1, $m2), $p)), $p);

        return \gmp_add($m2, \gmp_mul($h, $q));
    }

    public static function getKeyDetails($key): array
    {
        $details = \openssl_pkey_get_details($key);
        if (!isset($details['rsa'])) {
            throw new \UnexpectedValueException('Invalid RSA key');
        }

        $parts = $details['rsa'];
        $modulusLen = \mb_strlen($parts['n'], '8bit');

        foreach ($parts as &$v) {
            $v = self::convertOctetStringToInteger($v);
        }

        return [$parts, $modulusLen];
    }

    public static function convertOctetStringToInteger(string $x): \GMP
    {
        $value = '0x' . \bin2hex($x);

        return \gmp_init($value, 16);
    }

    public static function convertIntegerToOctetString(\GMP $x, int $xLen): string
    {
        $s = self::gmpToBytes($x);
        if (\strlen($s) > $xLen) {
            return false;
        }

        return \str_pad($s, $xLen, \chr(0), STR_PAD_LEFT);
    }


    public static function gmpToBytes(\GMP $value): string
    {
        if (0 === \gmp_cmp($value, \gmp_init(0))) {
            return '';
        }

        $temp = \gmp_strval(\gmp_abs($value), 16);
        $temp = (\mb_strlen($temp, '8bit') & 1) ? '0'.$temp : $temp;
        $temp = \hex2bin($temp);

        return \ltrim($temp, \chr(0));
    }
}