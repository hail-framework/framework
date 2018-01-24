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

/**
 * Class ASNObject is the base class for all concrete ASN.1 objects.
 */
abstract class ASNObject
{
    protected $contentLength;
    protected $nrOfLengthOctets;

    protected const TYPE = null;

    public function __construct($contentLength = null)
    {
        if ($contentLength === null) {
            $contentLength = $this->calculateContentLength();
        }

        $this->contentLength = $contentLength;

        $this->nrOfLengthOctets = 1;
        if ($contentLength > 127) {
            do { // long form
                ++$this->nrOfLengthOctets;
                $contentLength >>= 8;
            } while ($contentLength > 0);
        }
    }

    /**
     * Must return the number of octets of the content part.
     *
     * @return int
     */
    abstract protected function calculateContentLength();

    /**
     * Encode the object using DER encoding.
     *
     * @see http://en.wikipedia.org/wiki/X.690#DER_encoding
     *
     * @return string the binary representation of an objects value
     */
    abstract protected function getEncodedValue();

    /**
     * Return the content of this object in a non encoded form.
     * This can be used to print the value in human readable form.
     *
     * @return mixed
     */
    abstract public function getContent();

    /**
     * Encode this object using DER encoding.
     *
     * @return string the full binary representation of the complete object
     */
    public function getBinary()
    {
        $result = \chr(static::TYPE);
        $result .= $this->createLengthPart();
        $result .= $this->getEncodedValue();

        return $result;
    }

    private function createLengthPart()
    {
        $contentLength = $this->contentLength;
        $nrOfLengthOctets = $this->nrOfLengthOctets;

        if ($nrOfLengthOctets === 1) {
            return \chr($contentLength);
        }

        // the first length octet determines the number subsequent length octets
        $lengthOctets = \chr(0x80 | ($nrOfLengthOctets - 1));
        for ($shiftLength = 8 * ($nrOfLengthOctets - 2); $shiftLength >= 0; $shiftLength -= 8) {
            $lengthOctets .= \chr($contentLength >> $shiftLength);
        }

        return $lengthOctets;
    }
    /**
     * Returns the length of the whole object (including the identifier and length octets).
     */
    public function getObjectLength()
    {
        return 1 + $this->nrOfLengthOctets + $this->contentLength;
    }

    protected static function parseIdentifier($identifierOctet, $typeName)
    {
        if (\is_string($identifierOctet) || !\is_numeric($identifierOctet)) {
            $identifierOctet = \ord($identifierOctet);
        }

        if ($identifierOctet !== static::TYPE) {
            throw new \UnexpectedValueException("Can not create ASN.1 $typeName'");
        }
    }

    protected static function parseContentLength(&$binaryData, &$offsetIndex, $minimumLength = 0)
    {
        if (\strlen($binaryData) <= $offsetIndex) {
            throw new \UnexpectedValueException("Can not parse binary from data: Offset index `$offsetIndex` larger than input size");
        }

        $contentLength = \ord($binaryData[$offsetIndex++]);
        if (($contentLength & 0x80) !== 0) {
            // bit 8 is set -> this is the long form
            $nrOfLengthOctets = $contentLength & 0x7F;
            $contentLength = 0x00;
            $len = \strlen($binaryData);
            for ($i = 0; $i < $nrOfLengthOctets; $i++) {
                if ($len <= $offsetIndex) {
                    throw new \UnexpectedValueException("Can not parse content length (long form) from data: Offset index `$offsetIndex` larger than input size");
                }
                $contentLength = ($contentLength << 8) + \ord($binaryData[$offsetIndex++]);
            }
        }

        if ($contentLength < $minimumLength) {
            throw new \UnexpectedValueException('A ' . \get_called_class() . " should have a content length of at least {$minimumLength}. Extracted length was {$contentLength}",
                $offsetIndex);
        }

        return $contentLength;
    }
}
