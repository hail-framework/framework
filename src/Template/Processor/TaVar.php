<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaVar extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:var');
        if ($expression === null) {
            return false;
        }

        self::before($element, $expression);

        $element->removeAttribute('h:var');

        return false;
    }
}