<?php
namespace Hail\Template\Processor\Vue;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class VueHtml implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('v-html');
        if ($expression === null) {
            return false;
        }

        $expression = Syntax::parse($expression);

        Helpers::text($element, 'echo ' . $expression);

        $element->removeAttribute('v-html');

        return false;
    }
}