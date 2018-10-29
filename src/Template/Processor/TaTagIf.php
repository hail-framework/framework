<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaTagIf extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:tag-if');
        if ($expression === null) {
            return false;
        }

        $startCode = 'if (' . $expression . ') { echo ' . \var_export($element->getOpenTag(), 'true') . '; }';
        $endCode = 'if (' . $expression . ') { echo ' . \var_export($element->getCloseTag(), 'true') . '; }';

        self::before($element, $startCode);
        self::after($element, $endCode);

        foreach ($element->getChildren() as $child) {
            $element->insertBeforeSelf($child);
        }

        $element->remove();

        return false;
    }
}