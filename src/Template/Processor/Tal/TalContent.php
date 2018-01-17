<?php

namespace Hail\Template\Processor\Tal;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class TalContent implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('tal:content');
        if ($expression === null) {
            return false;
        }

        [$structure, $expression] = Syntax::isStructure($expression);
        $expression = Syntax::resolveWithDefault($expression, $element);
        $expression = Syntax::structure($structure, $expression);

        Helpers::text($element, $expression);

        $element->removeAttribute('tal:content');

        return false;
    }
}