<?php

namespace Hail\Template\Processor\Tal;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\ProcessorInterface;

final class TalAttributes implements ProcessorInterface
{
    /**
     * @var Element
     */
    private static $element;

    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('tal:attributes');
        if ($expression === null) {
            return false;
        }

        self::$element = $element;

        $result = Syntax::multiLine($expression, [self::class, 'resolve']);

        foreach ($result as $v) {
            if ($v === null) {
                continue;
            }

            [$attr, $exp] = $v;
            $element->setAttribute($attr, '<?php echo ' . $exp . ' ?>');
        }

        $element->removeAttribute('tal:attributes');

        return false;
    }

    public static function resolve(string $expression)
    {
        $expression = \trim($expression);
        if ($expression === '') {
            return null;
        }

        [$attr, $val] = \explode(' ', $expression, 2);

        return self::resolveValue($attr, $val);
    }

    private static function resolveValue(string $attr, string $expression)
    {
        [$keyword, $exp] = Syntax::lastKeyword($expression);

        switch ($keyword) {
            case 'nothing':
                [$attr, $exp] = self::resolveValue($attr, $exp);
                self::$element->removeAttribute($attr);

                return ['php:' . $attr, $exp];

            case 'default':
                $default = self::$element->getAttribute($attr) ?? '';
                [$attr, $exp] = self::resolveValue($attr, $exp);

                return [$attr, "($exp) ?: " . \var_export($default, true)];

            default:
                if ($keyword !== null) {
                    $exp .= ' | ' . $keyword;
                }

                return [$attr, Syntax::resolve($exp)];
        }
    }
}