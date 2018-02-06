<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Expression\Expression;
use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Processor;
use Hail\Template\Tokenizer\Token\TokenInterface;

final class VueShow extends Processor
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
        self::addStyle($element, $style);

        $element->removeAttribute('v-show');

        return false;
    }
}