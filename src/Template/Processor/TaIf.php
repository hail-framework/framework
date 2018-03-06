<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaIf extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:if');
        if ($expression === null) {
            return false;
        }

        $startCode = 'if (' . $expression . ') {';
        $endCode = '}';

        self::before($element, $startCode);
        self::after($element, $endCode);

        self::processElseIf($element);

        $element->removeAttribute('h:if');

        return false;
    }

    private static function processElseIf(Element $element): void
    {
        $next = $element->getNextSibling();
        if (!$next instanceof Element) {
            return;
        }

        if (!$next->hasAttribute('h:elseif')) {
            self::processElse($element);

            return;
        }

        $expression = $next->getAttribute('h:elseif');

        $startCode = 'elseif (' . $expression . ') {';
        $endCode = '}';

        self::before($next, $startCode);
        self::after($next, $endCode);

        self::processElse($next);

        $next->removeAttribute('h:elseif');
    }

    private static function processElse(Element $element): void
    {
        $next = $element->getNextSibling();
        if (!$next instanceof Element || !$next->hasAttribute('h:else')) {
            return;
        }

        $startCode = 'else {';
        $endCode = '}';

        self::before($next, $startCode);
        self::after($next, $endCode);

        $next->removeAttribute('h:else');
    }
}