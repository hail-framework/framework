<?php

namespace Hail\Spout\Reader;

/**
 * Interface IteratorInterface
 *
 * @package Hail\Spout\Reader
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
