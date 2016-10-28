<?php

namespace Hail\Excel\Reader\XLSX\Helper;

use Hail\Excel\Reader\Wrapper\XMLReader;
use Hail\Excel\Reader\XLSX\Sheet;

/**
 * Class SheetHelper
 * This class provides helper functions related to XLSX sheets
 *
 * @package Hail\Excel\Reader\XLSX\Helper
 */
class SheetHelper
{
    /** Paths of XML files relative to the XLSX file root */
    const WORKBOOK_XML_RELS_FILE_PATH = 'xl/_rels/workbook.xml.rels';
    const WORKBOOK_XML_FILE_PATH = 'xl/workbook.xml';

    /** @var string Path of the XLSX file being read */
    protected $filePath;

	/** @var \Hail\Excel\Reader\XLSX\ReaderOptions Reader's current options */
    protected $options;

    /** @var \Hail\Excel\Reader\XLSX\Helper\SharedStringsHelper Helper to work with shared strings */
    protected $sharedStringsHelper;

    /**
     * @param string $filePath Path of the XLSX file being read
     * @param \Hail\Excel\Reader\XLSX\ReaderOptions $options Reader's current options
     * @param \Hail\Excel\Reader\XLSX\Helper\SharedStringsHelper Helper to work with shared strings
     */
    public function __construct($filePath, $options, $sharedStringsHelper)
    {
        $this->filePath = $filePath;
	    $this->options = $options;
        $this->sharedStringsHelper = $sharedStringsHelper;
    }

    /**
     * Returns the sheets metadata of the file located at the previously given file path.
     * The paths to the sheets' data are read from the [Content_Types].xml file.
     *
     * @return Sheet[] Sheets within the XLSX file
     */
    public function getSheets()
    {
        $sheets = [];
        $sheetIndex = 0;

        $xmlReader = new XMLReader();
        if ($xmlReader->openFileInZip($this->filePath, self::WORKBOOK_XML_FILE_PATH)) {
            while ($xmlReader->read()) {
                if ($xmlReader->isPositionedOnStartingNode('sheet')) {
                    $sheets[] = $this->getSheetFromSheetXMLNode($xmlReader, $sheetIndex);
                    $sheetIndex++;
                } else if ($xmlReader->isPositionedOnEndingNode('sheets')) {
                    // stop reading once all sheets have been read
                    break;
                }
            }

            $xmlReader->close();
        }

        return $sheets;
    }

    /**
     * Returns an instance of a sheet, given the XML node describing the sheet - from "workbook.xml".
     * We can find the XML file path describing the sheet inside "workbook.xml.res", by mapping with the sheet ID
     * ("r:id" in "workbook.xml", "Id" in "workbook.xml.res").
     *
     * @param \Hail\Excel\Reader\Wrapper\XMLReader $xmlReaderOnSheetNode XML Reader instance, pointing on the node describing the sheet, as defined in "workbook.xml"
     * @param int $sheetIndexZeroBased Index of the sheet, based on order of appearance in the workbook (zero-based)
     * @return \Hail\Excel\Reader\XLSX\Sheet Sheet instance
     */
    protected function getSheetFromSheetXMLNode($xmlReaderOnSheetNode, $sheetIndexZeroBased)
    {
        $sheetId = $xmlReaderOnSheetNode->getAttribute('r:id');
        $escapedSheetName = $xmlReaderOnSheetNode->getAttribute('name');

        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $escaper = \Hail\Excel\Common\Escaper\XLSX::getInstance();
        $sheetName = $escaper->unescape($escapedSheetName);

        $sheetDataXMLFilePath = $this->getSheetDataXMLFilePathForSheetId($sheetId);

	    return new Sheet($this->filePath, $sheetDataXMLFilePath, $sheetIndexZeroBased, $sheetName, $this->options, $this->sharedStringsHelper);
    }

    /**
     * @param string $sheetId The sheet ID, as defined in "workbook.xml"
     * @return string The XML file path describing the sheet inside "workbook.xml.res", for the given sheet ID
     */
    protected function getSheetDataXMLFilePathForSheetId($sheetId)
    {
        $sheetDataXMLFilePath = '';

        // find the file path of the sheet, by looking at the "workbook.xml.res" file
        $xmlReader = new XMLReader();
        if ($xmlReader->openFileInZip($this->filePath, self::WORKBOOK_XML_RELS_FILE_PATH)) {
            while ($xmlReader->read()) {
                if ($xmlReader->isPositionedOnStartingNode('Relationship')) {
                    $relationshipSheetId = $xmlReader->getAttribute('Id');

                    if ($relationshipSheetId === $sheetId) {
                        // In workbook.xml.rels, it is only "worksheets/sheet1.xml"
                        // In [Content_Types].xml, the path is "/xl/worksheets/sheet1.xml"
                        $sheetDataXMLFilePath = $xmlReader->getAttribute('Target');

                        // sometimes, the sheet data file path already contains "/xl/"...
                        if (strpos($sheetDataXMLFilePath, '/xl/') !== 0) {
                            $sheetDataXMLFilePath = '/xl/' . $sheetDataXMLFilePath;
                            break;
                        }
                    }
                }
            }

            $xmlReader->close();
        }

        return $sheetDataXMLFilePath;
    }
}
