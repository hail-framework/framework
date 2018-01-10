<?php

namespace Hail\Excel\Reader\CSV;

use Hail\Excel\Reader\SheetInterface;

/**
 * Class Sheet
 *
 * @package Hail\Excel\Reader\CSV
 */
class Sheet implements SheetInterface
{
    /** @var \Hail\Excel\Reader\CSV\RowIterator To iterate over the CSV's rows */
    protected $rowIterator;

    /**
     * @param resource $filePointer Pointer to the CSV file to read
     * @param \Hail\Excel\Reader\CSV\ReaderOptions $options
     */
	public function __construct($filePointer, $options)
    {
	    $this->rowIterator = new RowIterator($filePointer, $options);
    }

    /**
     * @api
     * @return \Hail\Excel\Reader\CSV\RowIterator
     */
    public function getRowIterator()
    {
        return $this->rowIterator;
    }

    /**
     * @api
     * @return int Index of the sheet
     */
    public function getIndex()
    {
        return 0;
    }

    /**
     * @api
     * @return string Name of the sheet - empty string since CSV does not support that
     */
    public function getName()
    {
        return '';
    }

    /**
     * @api
     * @return bool Always TRUE as there is only one sheet
     */
    public function isActive()
    {
        return true;
    }
}
