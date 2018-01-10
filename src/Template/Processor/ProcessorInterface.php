<?php

namespace Hail\Template\Processor;

use Hail\Template\Html\Token\Element;

interface ProcessorInterface
{
    public static function process(Element $element): bool;
}