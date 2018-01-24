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
    private $contentLength;
    private $nrOfLengthOctets;

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
     * Return the object type octet.
     * This should use the class constants of Identifier.
     *
     * @see Identifier
     *
     * @return int
     */
    abstract public static function getType();

    /**
     * Returns all identifier octets. If an inheriting class models a tag with
     * the long form identifier format, it MUST reimplement this method to
     * return all octets of the identifier.
     *
     * @throws \LogicException If the identifier format is long form
     *
     * @return string Identifier as a set of octets
     */
    public function getIdentifier()
    {
        return \chr(static::getType());
    }

    /**
     * Encode this object using DER encoding.
     *
     * @return string the full binary representation of the complete object
     */
    public function getBinary()
    {
        $result = $this->getIdentifier();
        $result .= $this->createLengthPart();
        $result .= $this->getEncodedValue();

        return $result;
    }

    private function createLengthPart()
    {
        $contentLength = $this->getContentLength();
        $nrOfLengthOctets = $this->getNumberOfLengthOctets($contentLength);

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

    protected function getNumberOfLengthOctets($contentLength = null)
    {
        if (null === $this->nrOfLengthOctets) {
            if ($contentLength === null) {
                $contentLength = $this->getContentLength();
            }

            $this->nrOfLengthOctets = 1;
            if ($contentLength > 127) {
                do { // long form
                    $this->nrOfLengthOctets++;
                    $contentLength >>= 8;
                } while ($contentLength > 0);
            }
        }

        return $this->nrOfLengthOctets;
    }

    protected function getContentLength()
    {
        if (null === $this->contentLength) {
            $this->contentLength = $this->calculateContentLength();
        }

        return $this->contentLength;
    }

    protected function setContentLength($newContentLength)
    {
        $this->contentLength = $newContentLength;
        $this->getNumberOfLengthOctets($newContentLength);
    }

    /**
     * Returns the length of the whole object (including the identifier and length octets).
     */
    public function getObjectLength()
    {
        $nrOfIdentifierOctets = \strlen($this->getIdentifier());
        $contentLength = $this->getContentLength();
        $nrOfLengthOctets = $this->getNumberOfLengthOctets($contentLength);

        return $nrOfIdentifierOctets + $nrOfLengthOctets + $contentLength;
    }

    protected static function parseIdentifier($identifierOctet, $typeName)
    {
        if (\is_string($identifierOctet) || !\is_numeric($identifierOctet)) {
            $identifierOctet = \ord($identifierOctet);
        }

        if ($identifierOctet !== static::getType()) {
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
