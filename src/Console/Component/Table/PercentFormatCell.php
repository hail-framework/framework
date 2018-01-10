<?php
namespace Hail\Console\Component\Table;

use NumberFormatter;

class PercentFormatCell extends NumberFormatCell
{
    public function __construct($locale)
    {
        $this->locale = $locale;
        $this->formatter = new NumberFormatter($locale, NumberFormatter::PERCENT);
    }
}
