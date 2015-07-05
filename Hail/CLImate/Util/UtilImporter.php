<?php

namespace Hail\CLImate\Util;

trait UtilImporter
{
    /**
     * An instance of the UtilFactory
     *
     * @var \Hail\CLImate\Util\UtilFactory $util
     */
    protected $util;

    /**
     * Sets the $util property
     *
     * @param UtilFactory $util
     */
    public function util(UtilFactory $util)
    {
        $this->util = $util;
    }

}
