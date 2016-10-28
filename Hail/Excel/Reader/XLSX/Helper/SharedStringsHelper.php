<?php

namespace Hail\Excel\Reader\XLSX\Helper;

use Hail\Excel\Common\Exception\IOException;
use Hail\Excel\Reader\Exception\XMLProcessingException;
use Hail\Excel\Reader\Wrapper\SimpleXMLElement;
use Hail\Excel\Reader\Wrapper\XMLReader;
use Hail\Excel\Reader\XLSX\Helper\SharedStringsCaching\CachingStrategyFactory;
use Hail\Excel\Reader\XLSX\Helper\SharedStringsCaching\CachingStrategyInterface;

/**
 * Class SharedStringsHelper
 * This class provides helper functions for reading sharedStrings XML file
 *
 * @package Hail\Excel\Reader\XLSX\Helper
 */
class SharedStringsHelper
{
    /** Main namespace for the sharedStrings.xml file */
    const MAIN_NAMESPACE_FOR_SHARED_STRINGS_XML = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** @var string Temporary folder where the temporary files to store shared strings will be stored */
    protected $tempFolder;

    /** @var CachingStrategyInterface The best caching strategy for storing shared strings */
    protected $cachingStrategy;

    /**
     * @param string|null|void $tempFolder Temporary folder where the temporary files to store shared strings will be stored
     */
    public function __construct($tempFolder = null)
    {
        $this->tempFolder = $tempFolder;
    }

    /**
     * Builds an in-memory array containing all the shared strings of the sheet.
     * All the strings are stored in a XML file, located at 'xl/sharedStrings.xml'.
     * It is then accessed by the sheet data, via the string index in the built table.
     *
     * More documentation available here: http://msdn.microsoft.com/en-us/library/office/gg278314.aspx
     *
     * The XML file can be really big with sheets containing a lot of data. That is why
     * we need to use a XML reader that provides streaming like the XMLReader library.
     * Please note that SimpleXML does not provide such a functionality but since it is faster
     * and more handy to parse few XML nodes, it is used in combination with XMLReader for that purpose.
     *
     * @param string $xml
     * @return void
     * @throws \Hail\Excel\Common\Exception\IOException If sharedStrings.xml can't be read
     */
    public function extractSharedStrings(string $xml)
    {
        $xmlReader = new XMLReader();
	    $xmlReader->XML($xml);

        $sharedStringIndex = 0;
        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $escaper = \Hail\Excel\Common\Escaper\XLSX::getInstance();

        try {
            $sharedStringsUniqueCount = $this->getSharedStringsUniqueCount($xmlReader);
            $this->cachingStrategy = $this->getBestSharedStringsCachingStrategy($sharedStringsUniqueCount);

            $xmlReader->readUntilNodeFound('si');

            while ($xmlReader->name === 'si') {
	            $this->processSharedStringsItem($xmlReader, $sharedStringIndex, $escaper);
                $sharedStringIndex++;

                // jump to the next 'si' tag
                $xmlReader->next('si');
            }

	        $this->cachingStrategy->closeCache();

        } catch (XMLProcessingException $exception) {
            throw new IOException("The sharedStrings.xml file is invalid and cannot be read. [{$exception->getMessage()}]");
        }

        $this->cachingStrategy->closeCache();

        $xmlReader->close();
    }

    /**
     * Returns the shared strings unique count, as specified in <sst> tag.
     *
     * @param \Hail\Excel\Reader\Wrapper\XMLReader $xmlReader XMLReader instance
     * @return int|null Number of unique shared strings in the sharedStrings.xml file
     * @throws \Hail\Excel\Common\Exception\IOException If sharedStrings.xml is invalid and can't be read
     */
    protected function getSharedStringsUniqueCount($xmlReader)
    {
        $xmlReader->next('sst');

        // Iterate over the "sst" elements to get the actual "sst ELEMENT" (skips any DOCTYPE)
        while ($xmlReader->name === 'sst' && $xmlReader->nodeType !== XMLReader::ELEMENT) {
            $xmlReader->read();
        }

        $uniqueCount = $xmlReader->getAttribute('uniqueCount');

        // some software do not add the "uniqueCount" attribute but only use the "count" one
        // @see https://github.com/box/spout/issues/254
        if ($uniqueCount === null) {
            $uniqueCount = $xmlReader->getAttribute('count');
        }

        return ($uniqueCount !== null) ? (int) $uniqueCount : null;
    }

    /**
     * Returns the best shared strings caching strategy.
     *
     * @param int|null $sharedStringsUniqueCount Number of unique shared strings (NULL if unknown)
     * @return CachingStrategyInterface
     */
    protected function getBestSharedStringsCachingStrategy($sharedStringsUniqueCount)
    {
        return CachingStrategyFactory::getInstance()
                ->getBestCachingStrategy($sharedStringsUniqueCount, $this->tempFolder);
    }

	/**
     * Processes the shared strings item XML node which the given XML reader is positioned on.
     *
     * @param \Hail\Excel\Reader\Wrapper\XMLReader $xmlReader
     * @param int $sharedStringIndex Index of the processed shared strings item
     * @param \Hail\Excel\Common\Escaper\XLSX $escaper Helper to escape values
     * @return void
     */
    protected function processSharedStringsItem($xmlReader, $sharedStringIndex, $escaper)
    {
        $node = $this->getSimpleXmlElementNodeFromXMLReader($xmlReader);
        $node->registerXPathNamespace('ns', self::MAIN_NAMESPACE_FOR_SHARED_STRINGS_XML);

        // removes nodes that should not be read, like the pronunciation of the Kanji characters
        $cleanNode = $this->removeSuperfluousTextNodes($node);

        // find all text nodes "t"; there can be multiple if the cell contains formatting
        $textNodes = $cleanNode->xpath('//ns:t');

        $textValue = $this->extractTextValueForNodes($textNodes);
        $unescapedTextValue = $escaper->unescape($textValue);

        $this->cachingStrategy->addStringForIndex($unescapedTextValue, $sharedStringIndex);
    }

    /**
     * Returns a SimpleXMLElement node from the current node in the given XMLReader instance.
     * This is to simplify the parsing of the subtree.
     *
     * @param \Hail\Excel\Reader\Wrapper\XMLReader $xmlReader
     * @return \Hail\Excel\Reader\Wrapper\SimpleXMLElement
     * @throws \Hail\Excel\Common\Exception\IOException If the current node cannot be read
     */
    protected function getSimpleXmlElementNodeFromXMLReader($xmlReader)
    {
        $node = null;
        try {
            $node = new SimpleXMLElement($xmlReader->readOuterXml());
        } catch (XMLProcessingException $exception) {
            throw new IOException("The sharedStrings.xml file contains unreadable data [{$exception->getMessage()}].");
        }

        return $node;
    }

    /**
     * Removes nodes that should not be read, like the pronunciation of the Kanji characters.
     * By keeping them, their text content would be added to the read string.
     *
     * @param \Hail\Excel\Reader\Wrapper\SimpleXMLElement $parentNode Parent node that may contain nodes to remove
     * @return \Hail\Excel\Reader\Wrapper\SimpleXMLElement Cleaned parent node
     */
    protected function removeSuperfluousTextNodes($parentNode)
    {
        $tagsToRemove = [
            'rPh', // Pronunciation of the text
            'pPr', // Paragraph Properties / Previous Paragraph Properties
            'rPr', // Run Properties for the Paragraph Mark / Previous Run Properties for the Paragraph Mark
        ];

        foreach ($tagsToRemove as $tagToRemove) {
            $xpath = '//ns:' . $tagToRemove;
            $parentNode->removeNodesMatchingXPath($xpath);
        }

        return $parentNode;
    }

	/**
     * @param array $textNodes Text XML nodes ("<t>")
     * @return string The value associated with the given text node(s)
     */
    protected function extractTextValueForNodes($textNodes)
    {
        $textValue = '';

        foreach ($textNodes as $nodeIndex => $textNode) {
            if ($nodeIndex !== 0) {
                // add a space between each "t" node
                $textValue .= ' ';
            }

            $textNodeAsString = $textNode->__toString();
            $shouldPreserveWhitespace = $this->shouldPreserveWhitespace($textNode);

            $textValue .= $shouldPreserveWhitespace ? $textNodeAsString : trim($textNodeAsString);
        }

        return $textValue;
    }

    /**
     * If the text node has the attribute 'xml:space="preserve"', then preserve whitespace.
     *
     * @param \Hail\Excel\Reader\Wrapper\SimpleXMLElement $textNode The text node element (<t>) whitespace may be preserved
     * @return bool Whether whitespace should be preserved
     */
    protected function shouldPreserveWhitespace($textNode)
    {
        $spaceValue = $textNode->getAttribute('space', 'xml');
        return ($spaceValue === 'preserve');
    }

    /**
     * Returns the shared string at the given index, using the previously chosen caching strategy.
     *
     * @param int $sharedStringIndex Index of the shared string in the sharedStrings.xml file
     * @return string The shared string at the given index
     * @throws \Hail\Excel\Reader\Exception\SharedStringNotFoundException If no shared string found for the given index
     */
    public function getStringAtIndex($sharedStringIndex)
    {
        return $this->cachingStrategy->getStringAtIndex($sharedStringIndex);
    }

    /**
     * Destroys the cache, freeing memory and removing any created artifacts
     *
     * @return void
     */
    public function cleanup()
    {
        if ($this->cachingStrategy) {
            $this->cachingStrategy->clearCache();
        }
    }
}
