<?php

namespace Hail\Excel\Writer\Exception\Border;

use Hail\Excel\Writer\Exception\WriterException;
use Hail\Excel\Writer\Style\BorderPart;

class InvalidNameException extends WriterException
{
    public function __construct($name)
    {
        $msg = '%s is not a valid name identifier for a border. Valid identifiers are: %s.';

        parent::__construct(sprintf($msg, $name, implode(',', BorderPart::getAllowedNames())));
    }
}
