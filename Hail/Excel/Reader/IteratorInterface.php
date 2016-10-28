<?php

namespace Hail\Excel\Reader;

/**
 * Interface IteratorInterface
 *
 * @package Hail\Excel\Reader
 */
interface IteratorInterface extends \Iterator
{
    /**
     * Cleans up what was created to iterate over the object.
     *
     * @return void
     */
    public function end();
}
