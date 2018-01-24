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

    private function __construct($children)
    {
        $this->children = $children;
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
     * @return ASNObject[]
     */
    public function getContent()
    {
        return $this->children;
    }

    public static function getType()
    {
        return 0x30;
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

        $parsedObject = new static($children);
        $parsedObject->setContentLength($contentLength);

        return $parsedObject;
    }
}
