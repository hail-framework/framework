<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class VueIf implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('v-if');
        if ($expression === null) {
            return false;
        }

        $expression = Syntax::parse($expression);

        $startCode = 'if (' . $expression . ') {';
        $endCode = '}';

        Helpers::before($element, $startCode);
        Helpers::after($element, $endCode);

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

        $expression = Syntax::parse($element->getAttribute('v-else-if'));

        $startCode = 'elseif (' . $expression . ') {';
        $endCode = '}';

        Helpers::before($next, $startCode);
        Helpers::after($next, $endCode);

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

        Helpers::before($next, $startCode);
        Helpers::after($next, $endCode);

        $next->removeAttribute('v-else');
    }
}