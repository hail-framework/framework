<?php

namespace Hail\Template\Processor\Tal;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class TalOmitTag implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('tal:omit-tag');
        if ($expression === null && $element->getName() !== 'tal:block') {
            return false;
        }

        if ($expression === null) {
            $expression = '';
        } else {
            $expression = \trim($expression);
        }

        if ($expression !== '') {
            $expression = Syntax::resolve($expression);

            $startCode = 'if (' . $expression . ') { echo ' . \var_export($element->getOpenTag(), 'true') . '; }';
            $endCode = 'if (' . $expression . ') { echo ' . \var_export($element->getCloseTag(), 'true') . '; }';

            Helpers::before($element, $startCode);
            Helpers::after($element, $endCode);
        }

        foreach ($element->getChildren() as $child) {
            $element->insertBeforeSelf($child);
        }

        $element->remove();

        return true;
    }
}