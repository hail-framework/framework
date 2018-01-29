<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\ProcessorInterface;
use Hail\Template\Tokenizer\Token\Text;

final class TextVar implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        if (!$element instanceof Text) {
            return false;
        }

        $text = $element->getValue();

        $regex = '/\{\{(?P<expression>.*?)\}\}/x';
        \preg_match_all($regex, $text, $matches);

        if ($matches['expression'] !== []) {
            foreach ($matches['expression'] as $index => $expression) {
                $value = VuePhp::$parser->parse($expression)
                    ->toExpression();

                $text = \str_replace($matches[0][$index], $value, $text);
            }

            $element->setValue($text);
        }

        return false;
    }
}