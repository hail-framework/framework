<?php

namespace Hail\Template\Processor\Tal;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class TalReplace implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('tal:replace');
        if ($expression === null) {
            return false;
        }

        [$structure, $expression] = Syntax::isStructure($expression);
        $expression = Syntax::resolve($expression);

        if ($expression !== '') {
            $expression = Syntax::structure($structure, $expression);

            Helpers::before($element, $expression);
        }

        $element->remove();

        return true;
    }
}