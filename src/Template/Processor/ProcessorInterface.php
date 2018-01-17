<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\Element;

interface ProcessorInterface
{
    public static function process(Element $element): bool;
}