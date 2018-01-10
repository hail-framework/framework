<?php

namespace Hail\Excel\Reader\Wrapper;

/**
 * Class XMLReader
 * Wrapper around the built-in XMLReader
 * @see \XMLReader
 *
 * @package Hail\Excel\Reader\Wrapper
 */
class XMLReader extends \XMLReader
{
    use XMLInternalErrorsHelper;

    /**
     * Opens the XML Reader to read a file located inside a ZIP file.
     *
     * @param string $zipFilePath Path to the ZIP file
     * @param string $fileInsideZipPath Relative or absolute path of the file inside the zip
     * @return bool TRUE on success or FALSE on failure
     */
    public function openFileInZip($zipFilePath, $fileInsideZipPath)
    {
        $wasOpenSuccessful = false;
	    $zipFilePath = realpath($zipFilePath);

	    $zip = new \ZipArchive();
	    if ($zip->open($zipFilePath) === true) {
		    if (($index = $zip->locateName($fileInsideZipPath)) !== false) {
			    $wasOpenSuccessful = $this->XML($zip->getFromIndex($index), null, LIBXML_NONET);
		    }
		    $zip->close();
	    }

        return $wasOpenSuccessful;
    }

    /**
     * Move to next node in document
     * @see \XMLReader::read
     *
     * @return bool TRUE on success or FALSE on failure
     * @throws \Hail\Excel\Reader\Exception\XMLProcessingException If an error/warning occurred
     */
    public function read()
    {
        $this->useXMLInternalErrors();

        $wasReadSuccessful = parent::read();

        $this->resetXMLInternalErrorsSettingAndThrowIfXMLErrorOccured();

        return $wasReadSuccessful;
    }

    /**
     * Read until the element with the given name is found, or the end of the file.
     *
     * @param string $nodeName Name of the node to find
     * @return bool TRUE on success or FALSE on failure
     * @throws \Hail\Excel\Reader\Exception\XMLProcessingException If an error/warning occurred
     */
    public function readUntilNodeFound($nodeName)
    {
        do {
            $wasReadSuccessful = $this->read();
            $isNotPositionedOnStartingNode = !$this->isPositionedOnStartingNode($nodeName);
        } while ($wasReadSuccessful && $isNotPositionedOnStartingNode);

        return $wasReadSuccessful;
    }

    /**
     * Move cursor to next node skipping all subtrees
     * @see \XMLReader::next
     *
     * @param string|void $localName The name of the next node to move to
     * @return bool TRUE on success or FALSE on failure
     * @throws \Hail\Excel\Reader\Exception\XMLProcessingException If an error/warning occurred
     */
    public function next($localName = null)
    {
        $this->useXMLInternalErrors();

        $wasNextSuccessful = parent::next($localName);

        $this->resetXMLInternalErrorsSettingAndThrowIfXMLErrorOccured();

        return $wasNextSuccessful;
    }

    /**
     * @param string $nodeName
     * @return bool Whether the XML Reader is currently positioned on the starting node with given name
     */
    public function isPositionedOnStartingNode($nodeName)
    {
        return $this->isPositionedOnNode($nodeName, XMLReader::ELEMENT);
    }

    /**
     * @param string $nodeName
     * @return bool Whether the XML Reader is currently positioned on the ending node with given name
     */
    public function isPositionedOnEndingNode($nodeName)
    {
        return $this->isPositionedOnNode($nodeName, XMLReader::END_ELEMENT);
    }

    /**
     * @param string $nodeName
     * @param int $nodeType
     * @return bool Whether the XML Reader is currently positioned on the node with given name and type
     */
    private function isPositionedOnNode($nodeName, $nodeType)
    {
        // In some cases, the node has a prefix (for instance, "<sheet>" can also be "<x:sheet>").
        // So if the given node name does not have a prefix, we need to look at the unprefixed name ("localName").
        // @see https://github.com/box/spout/issues/233
        $hasPrefix = (strpos($nodeName, ':') !== false);
        $currentNodeName = ($hasPrefix) ? $this->name : $this->localName;

        return ($this->nodeType === $nodeType && $currentNodeName === $nodeName);
    }

    /**
     * @return string The name of the current node, un-prefixed
     */
    public function getCurrentNodeName()
    {
        return $this->localName;
    }
}
