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
}
