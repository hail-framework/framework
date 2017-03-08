<?php

namespace Hail\Console\Util;

trait OutputImportTrait
{
    /**
     * An instance of the OutputFactory
     *
     * @var \Hail\Console\Util\Output $output
     */
    protected $output;

    /**
     * Sets the $output property
     *
     * @param Output $output
     */
    public function output(Output $output)
    {
        $this->output = $output;
    }
}
