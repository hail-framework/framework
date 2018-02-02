<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Expression\Expression;
use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;
use Hail\Template\Tokenizer\Token\TokenInterface;

final class VueShow implements ProcessorInterface
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('v-show');
        if ($expression === null) {
            return false;
        }

        if ($element->getName() === 'template') {
            throw  new \LogicException('v-show not support for template tag');
        }

        $expression = Expression::parse($expression);

        $style = '<?=(' . $expression . ') ? \'\': \'display: none\'?>';
        Helpers::addStyle($element, $style);

        $element->removeAttribute('v-show');

        return false;
    }
}