<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class VueShow implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('v-show');
        if ($expression === null) {
            return false;
        }

        if ($element->getName() === 'template') {
            throw  new \LogicException('v-show not support for template tag');
        }

        $expression = \trim($expression);

        $style = '<?php echo (' . $expression . ') ? \'\': \'display: none\' ?>';
        Helpers::addStyle($element, $style);

        $element->removeAttribute('v-show');

        return false;
    }
}