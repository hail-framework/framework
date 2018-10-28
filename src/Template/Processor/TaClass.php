<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaClass extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:class');
        if ($expression === null) {
            return false;
        }

        $class = [
            (string) $element->getAttribute('class')
        ];

        $expressions = \explode(',', $expression);
        foreach ($expressions as $v) {
            $class[] = '<?=' . \trim($v) . '?>';
        }

        $element->setAttribute('class', \implode(' ', $class));
        $element->removeAttribute('h:class');

        return false;
    }
}