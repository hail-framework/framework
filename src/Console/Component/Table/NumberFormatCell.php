<?php
namespace Hail\Console\Component\Table;

use NumberFormatter;

class NumberFormatCell extends CellAttribute
{
    protected $locale;

    public function __construct($locale)
    {
        $this->locale = $locale;
        $this->formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
    }

    public function format($cell)
    {
        if (\is_numeric($cell)) {
            return $this->formatter->format($cell);
        }
        return $cell;
    }
}
