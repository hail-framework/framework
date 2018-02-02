<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\TokenInterface;

interface ProcessorInterface
{
    public static function process(TokenInterface $element): bool;
}