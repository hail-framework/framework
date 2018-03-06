<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaFor extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:for');
        if ($expression === null) {
            return false;
        }

        $int = (int) $expression;
        if ((string) $int === $expression) {
            $expression = '$i = 0; $i < ' . $expression . '; ++$i';
        }

        $startCode = 'for (' . $expression . ') {';
        $endCode = '}';

        self::before($element, $startCode);
        self::after($element, $endCode);

        $element->removeAttribute('h:for');

        return false;
    }
}