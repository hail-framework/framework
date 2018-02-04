<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Expression\Expression;
use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\ProcessorInterface;
use Hail\Template\Tokenizer\Token\TokenInterface;

final class VueBind implements ProcessorInterface
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        foreach (self::findBindAttribute($element) as $attr => $val) {
            $value = Expression::parseWithFilters($val);

            $element->setAttribute($attr, '<?=' . $value . '?>');
            $element->removeAttribute($attr);
        }

        return false;
    }

    private static function findBindAttribute(Element $element)
    {
        foreach ($element->getAttributes() as $attribute) {
            $attr = $attribute->nodeName;
            if ($attr[0] === ':' || \strpos($attr, 'v-bind:') === 0) {
                $attr = \explode(':', $attr, 2)[1];
                yield $attr => \trim($attribute->nodeValue);
            }
        }
    }

}