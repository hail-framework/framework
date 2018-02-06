<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Expression\Expression;
use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Processor;
use Hail\Template\Tokenizer\Token\TokenInterface;

final class VueIf extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('v-if');
        if ($expression === null) {
            return false;
        }

        $expression = Expression::parse($expression);

        $startCode = 'if (' . $expression . ') {';
        $endCode = '}';

        self::before($element, $startCode);
        self::after($element, $endCode);

        self::processElseIf($element);

        $element->removeAttribute('v-if');

        return false;
    }

    private static function processElseIf(Element $element): void
    {
        $next = $element->getNextSibling();
        if (!$next instanceof Element) {
            return;
        }

        if (!$next->hasAttribute('v-else-if')) {
            self::processElse($element);

            return;
        }

        $expression = Expression::parse($element->getAttribute('v-else-if'));

        $startCode = 'elseif (' . $expression . ') {';
        $endCode = '}';

        self::before($next, $startCode);
        self::after($next, $endCode);

        self::processElse($next);

        $next->removeAttribute('v-else-if');
    }

    private static function processElse(Element $element)
    {
        $next = $element->getNextSibling();
        if (!$next instanceof Element || !$next->hasAttribute('v-else')) {
            return;
        }

        $startCode = 'else {';
        $endCode = '}';

        self::before($next, $startCode);
        self::after($next, $endCode);

        $next->removeAttribute('v-else');
    }
}