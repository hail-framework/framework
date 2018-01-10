<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Html\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class VueFor implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('v-for');
        if ($expression === null) {
            return false;
        }

        $expression = \trim($expression);

        if (\strpos($expression, ' of ') !== false) {
            $delimiter = ' of ';
        } elseif (\strpos($expression, ' in ') !== false) {
            $delimiter = ' in ';
        } else {
            throw new \LogicException('v-for expression must have `in` or `of` syntax');
        }

        [$sub, $items] = \explode($delimiter, $expression, 2);

        $int = (int) $items;
        if (((string) $int) === $items) {
            $items = \var_export(\range(1, $int), true);
        } else {
            $items = '$' . $items;
        }

        $startCode = $endCode = '';

        $sub = \trim($sub);
        if ($sub[0] === '(' && $sub[\strlen($sub) - 1] === ')') {
            $sub = \substr($sub, 1, -1);
            $sub = \array_map('trim', \explode(',', $sub));

            switch (\count($sub)) {
                case 1:
                    $sub = '$' . $sub[0];
                    break;
                case 2:
                    $sub = '$' . $sub[1] . ' => $' . $sub[0];
                    break;
                case 3:
                    $startCode = '$' . $sub[2] . ' = 0; ';
                    $endCode = '++$' . $sub[2] . '; ';
                    $sub = '$' . $sub[1] . ' => $' . $sub[0];
                    break;

                default:
                    throw new \LogicException('v-for syntax error: ' . \implode(', ', $sub));
            }
        } else {
            $sub = '$' . $sub;
        }

        $startCode .= 'foreach (' . $items . ' as ' . $sub . ') { ';
        $endCode .= '} ';

        Helpers::before($element, $startCode);
        Helpers::after($element, $endCode);

        $element->removeAttribute('v-for');

        return false;
    }
}