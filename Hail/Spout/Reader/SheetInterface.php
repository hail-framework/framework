<?php

namespace Hail\Spout\Reader;

/**
 * Interface SheetInterface
 *
 * @package Hail\Spout\Reader
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
