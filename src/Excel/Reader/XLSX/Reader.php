<?php

namespace Hail\Excel\Reader\XLSX;

use Hail\Excel\Common\Exception\IOException;
use Hail\Excel\Reader\AbstractReader;
use Hail\Excel\Reader\XLSX\Helper\SharedStringsHelper;

/**
 * Class Reader
 * This class provides support to read data from a XLSX file
 *
 * @package Hail\Excel\Reader\XLSX
 */
class Reader extends AbstractReader
{
    /** @var \ZipArchive */
    protected $zip;

    /** @var \Hail\Excel\Reader\XLSX\Helper\SharedStringsHelper Helper to work with shared strings */
    protected $sharedStringsHelper;

    /** @var SheetIterator To iterator over the XLSX sheets */
    protected $sheetIterator;

	/**
     * Returns the reader's current options
     *
     * @return ReaderOptions
     */
    protected function getOptions()
    {
        if (!isset($this->options)) {
            $this->options = new ReaderOptions();
        }
        return $this->options;
    }

    /**
     * @param string $tempFolder Temporary folder where the temporary files will be created
     * @return Reader
     */
    public function setTempFolder($tempFolder)
    {
	    $this->getOptions()->setTempFolder($tempFolder);
        return $this;
    }

    /**
     * Returns whether stream wrappers are supported
     *
     * @return bool
     */
    protected function doesSupportStreamWrapper()
    {
        return false;
    }

    /**
     * Opens the file at the given file path to make it ready to be read.
     * It also parses the sharedStrings.xml file to get all the shared strings available in memory
     * and fetches all the available sheets.
     *
     * @param  string $filePath Path of the file to be read
     * @return void
     * @throws \Hail\Excel\Common\Exception\IOException If the file at the given path or its content cannot be read
     * @throws \Hail\Excel\Reader\Exception\NoSheetsFoundException If there are no sheets in the file
     */
    protected function openReader($filePath)
    {
        $this->zip = new \ZipArchive();

        if ($this->zip->open($filePath) === true) {
            $this->sharedStringsHelper = new SharedStringsHelper($this->getOptions()->getTempFolder());

            if (($index = $this->zip->locateName(SharedStringsHelper::SHARED_STRINGS_XML_FILE_PATH)) !== false) {
                // Extracts all the strings from the sheets for easy access in the future
                $this->sharedStringsHelper->extractSharedStrings(
                	$this->zip->getFromIndex($index)
                );
            } else {
	            throw new IOException('Could not open "' . SharedStringsHelper::SHARED_STRINGS_XML_FILE_PATH . '".');
            }
            $this->zip->close();

	        $this->sheetIterator = new SheetIterator($filePath, $this->getOptions(), $this->sharedStringsHelper);
	        $this->sheetIterator->rewind();
        } else {
            throw new IOException("Could not open $filePath for reading.");
        }
    }

    /**
     * Returns an iterator to iterate over sheets.
     *
     * @return SheetIterator To iterate over sheets
     */
    protected function getConcreteSheetIterator()
    {
        return $this->sheetIterator;
    }

    /**
     * Closes the reader. To be used after reading the file.
     *
     * @return void
     */
    protected function closeReader()
    {
        if ($this->sharedStringsHelper) {
            $this->sharedStringsHelper->cleanup();
        }
    }
}
