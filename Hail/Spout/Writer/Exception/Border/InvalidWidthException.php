<?php

namespace Hail\Spout\Writer\Exception\Border;

use Hail\Spout\Writer\Exception\WriterException;
use Hail\Spout\Writer\Style\BorderPart;

class InvalidWidthException extends WriterException
{
    public function __construct($name)
    {
        $msg = '%s is not a valid width identifier for a border. Valid identifiers are: %s.';

        parent::__construct(sprintf($msg, $name, implode(',', BorderPart::getAllowedWidths())));
    }
}
