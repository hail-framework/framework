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

class Sequence extends ASNObject
{
    /** @var \Hail\Jose\Signature\ASN1\Integer[] */
    protected $children;

    protected const TYPE = 0x30;

    public function __construct($children, $contentLength = null)
    {
        $this->children = $children;

        parent::__construct($contentLength);
    }

    protected function calculateContentLength()
    {
        $length = 0;
        foreach ($this->children as $component) {
            $length += $component->getObjectLength();
        }

        return $length;
    }

    protected function getEncodedValue()
    {
        $result = '';
        foreach ($this->children as $component) {
            $result .= $component->getBinary();
        }

        return $result;
    }

    /**
     * @return \Hail\Jose\Signature\ASN1\Integer[]
     */
    public function getContent()
    {
        return $this->children;
    }

    /**
     * @param string $binaryData
     * @param int $offsetIndex
     *
     * @return static
     */
    public static function fromBinary(&$binaryData, &$offsetIndex = 0)
    {
        self::parseIdentifier($binaryData[$offsetIndex], 'Sequence');

        ++$offsetIndex;
        $contentLength = self::parseContentLength($binaryData, $offsetIndex);

        $children = [];
        $octetsToRead = $contentLength;
        while ($octetsToRead > 0) {
            $newChild = Integer::fromBinary($binaryData, $offsetIndex);
            $octetsToRead -= $newChild->getObjectLength();
            $children[] = $newChild;
        }

        return new static($children, $contentLength);
    }
}
