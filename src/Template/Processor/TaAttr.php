<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    TokenInterface, Element
};

class TaAttr extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('h:attr');
        if ($expression === null) {
            return false;
        }

        $expressions = \explode(',', $expression);
        foreach ($expressions as $v) {
            [$key, $value] = \explode('=>', $v);

            $key = \trim($key);
            $value = \trim($value);

            $element->setAttribute('php:' . $key, "<?=(\$_tmp = $value) ? '{$key}=\"' . \$_tmp . '\"' : ''?>");
        }

        $element->removeAttribute('h:attr');

        return false;
    }
}