<?php

namespace Hail\Console\Util;

trait UtilImportTrait
{
    /**
     * An instance of the UtilFactory
     *
     * @var \Hail\Console\Util\UtilFactory $util
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
