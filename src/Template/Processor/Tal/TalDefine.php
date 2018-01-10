<?php
namespace Hail\Template\Processor\Tal;

use Hail\Template\Html\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class TalDefine implements ProcessorInterface
{
    /**
     * @var Element
     */
    private static $element;

    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('tal:define');
        if ($expression === null) {
            return false;
        }

        self::$element = $element;

        $result = Syntax::multiLine($expression, [self::class, 'resolve']);
        Helpers::before($element, \implode('; ', $result));

        $element->removeAttribute('tal:define');

        return false;
    }

    public static function resolve(string $expression)
    {
        [$name, $var] = \explode(' ', $expression, 2);
        $name = Syntax::variable($name);
        $var = Syntax::resolveWithDefault($var, self::$element);

        return $name . ' = ' . $var;
    }
}