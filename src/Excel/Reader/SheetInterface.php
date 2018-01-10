<?php

namespace Hail\Excel\Reader;

/**
 * Interface SheetInterface
 *
 * @package Hail\Excel\Reader
 */
interface SheetInterface
{
    /**
     * Returns an iterator to iterate over the sheet's rows.
     *
     * @return \Iterator
     */
    public function getRowIterator();
}
