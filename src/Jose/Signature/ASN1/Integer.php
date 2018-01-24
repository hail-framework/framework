<?php
/*
 * This file is part of the PHPASN1 library.
 *
 * Copyright © Friedrich Große <friedrich.grosse@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Jose\Signature\ASN1;


class Integer extends ASNObject
{
    /** @var int */
    private $value;

    protected const TYPE = 0x02;

    public function __construct($value, $contentLength = null)
    {
        if (!\is_numeric($value)) {
            throw new \InvalidArgumentException("Invalid VALUE [{$value}] for ASN.1_Integer");
        }

        $this->value = $value;

        parent::__construct($contentLength);
    }

    public function getContent()
    {
        return $this->value;
    }

    protected function calculateContentLength()
    {
        $nrOfOctets = 1; // we need at least one octet
        $tmpValue = \gmp_abs(\gmp_init($this->value, 10));
        while (\gmp_cmp($tmpValue, 127) > 0) {
            $tmpValue = $this->rightShift($tmpValue, 8);
            $nrOfOctets++;
        }
        return $nrOfOctets;
    }

    /**
     * @param resource|\GMP $number
     * @param int $positions
     *
     * @return resource|\GMP
     */
    private function rightShift($number, int $positions)
    {
        // Shift 1 right = div / 2
        return \gmp_div($number, \gmp_pow(2, $positions));
    }

    protected function getEncodedValue()
    {
        $numericValue = \gmp_init($this->value, 10);
        $contentLength = $this->contentLength;

        if (\gmp_sign($numericValue) < 0) {
            $numericValue = \gmp_add($numericValue, \gmp_sub(\gmp_pow(2, 8 * $contentLength), 1));
            $numericValue = \gmp_add($numericValue, 1);
        }

        $result = '';
        for ($shiftLength = ($contentLength - 1) * 8; $shiftLength >= 0; $shiftLength -= 8) {
            $octet = \gmp_strval(\gmp_mod($this->rightShift($numericValue, $shiftLength), 256));
            $result .= \chr($octet);
        }

        return $result;
    }

    public static function fromBinary(&$binaryData, &$offsetIndex = 0)
    {
        self::parseIdentifier($binaryData[$offsetIndex], 'Integer');

        ++$offsetIndex;
        $contentLength = self::parseContentLength($binaryData, $offsetIndex, 1);

        $isNegative = (\ord($binaryData[$offsetIndex]) & 0x80) !== 0x00;
        $number = \gmp_init(\ord($binaryData[$offsetIndex++]) & 0x7F, 10);

        for ($i = 0; $i < $contentLength - 1; $i++) {
            $number = \gmp_or(\gmp_mul($number, 0x100), \ord($binaryData[$offsetIndex++]));
        }

        if ($isNegative) {
            $number = \gmp_sub($number, \gmp_pow(2, 8 * $contentLength - 1));
        }

        return new static(\gmp_strval($number, 10), $contentLength);
    }
}
