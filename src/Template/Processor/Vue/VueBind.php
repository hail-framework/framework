<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\ProcessorInterface;

final class VueBind implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        foreach (self::findBindAttribute($element) as $attr => $val) {
            $value = VuePhp::$parser->parse($val)->toExpression();

            $element->setAttribute($attr, '<?=' . $value . '?>');
            $element->removeAttribute($attr);
        }

        return false;
    }

    private static function findBindAttribute(Element $element)
    {
        foreach ($element->getAttributes() as $attribute) {
            $attr = $attribute->nodeName;
            if (
                \strpos($attr, 'v-bind:') === 0 ||
                \strpos($attr, ':') === 0
            ) {
                $attr = \explode(':', $attr, 2)[1];
                yield $attr => \trim($attribute->nodeValue);
            }
        }
    }

}