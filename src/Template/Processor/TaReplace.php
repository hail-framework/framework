<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaReplace extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:replace');
        if ($expression === null) {
            return false;
        }

        self::before($element, $expression);

        $element->remove();

        return true;
    }
}