<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Expression\Expression;
use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Processor;
use Hail\Template\Tokenizer\Token\TokenInterface;

final class VueFor extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('v-for');
        if ($expression === null) {
            return false;
        }

        if (\strpos($expression, ' of ') !== false) {
            $delimiter = ' of ';
        } elseif (\strpos($expression, ' in ') !== false) {
            $delimiter = ' in ';
        } else {
            throw new \LogicException('v-for expression must have `in` or `of` syntax');
        }

        [$sub, $items] = \explode($delimiter, $expression, 2);

        $items = \trim($items);

        $startCode = $endCode = '';

        $sub = \trim($sub);
        if ($sub[0] === '(' && $sub[-1] === ')') {
            $parts = \explode(',', \substr($sub, 1, -1), 3);
        } else {
            $parts = [$sub];
        }

        $val = '$' . \trim($parts[0]);

        $key = $c = null;
        if (isset($parts[1])) {
            $key = '$' . \trim($parts[1]);

            if (isset($parts[2])) {
                $c = '$' . \trim($parts[2]);
            }
        }

        $int = (int) $items;
        if (((string) $int) === $items) {
            $c = $c ?? '$_i';
            $startCode .= 'for (' . $c . ' = 0; ' . $c . ' < ' . $int . '; ++' . $c . ') { ';
            $startCode .= null !== $key ? $key . ' = ' : '';
            $startCode .= $val . ' = ' . $c . '; ';
        } else {
            if ($c !== null) {
                $startCode = $c . ' = 0; ';
                $endCode = '++' . $c . '; ';
            }

            $startCode .= 'foreach (' . Expression::parse($items) . ' as ';
            $startCode .= $key !== null ? $key . ' => ' : '';
            $startCode .=  $val . ') { ';
        }

        $endCode .= '} ';

        self::before($element, $startCode);
        self::after($element, $endCode);

        $element->removeAttribute('v-for');

        return false;
    }
}