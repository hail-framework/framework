<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaSwitch extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:switch');
        if ($expression === null) {
            return false;
        }

        $startCode = 'switch (' . $expression . ') {';
        $endCode = '}';

        self::before($element, $startCode);
        self::after($element, $endCode);

        self::processChildren($element);

        $element->removeAttribute('h:switch');

        return false;
    }

    private static function processChildren(Element $element): void
    {
        foreach ($element->getChildren() as $child) {
            if (!$child instanceof Element) {
                continue;
            }

            if ($expression = $child->hasAttribute('h:case')) {
                $startCode = 'case ' . $expression . ':';
                $endCode = 'break;';

                self::before($child, $startCode);
                self::after($child, $endCode);

                $child->removeAttribute('h:case');
            }

            if ($expression = $child->hasAttribute('h:default')) {
                $startCode = 'default:';
                $endCode = 'break;';

                self::before($child, $startCode);
                self::after($child, $endCode);

                $child->removeAttribute('h:default');

                break;
            }
        }

    }
}