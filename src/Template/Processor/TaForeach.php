<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaForeach extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:foreach');
        if ($expression === null) {
            return false;
        }

        $startCode = 'foreach (' . $expression . ') {';
        $endCode = '}';

        self::before($element, $startCode);
        self::after($element, $endCode);

        $element->removeAttribute('h:foreach');

        return false;
    }
}