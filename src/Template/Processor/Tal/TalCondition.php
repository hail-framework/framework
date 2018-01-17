<?php
namespace Hail\Template\Processor\Tal;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class TalCondition implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('tal:condition');
        if ($expression === null) {
            return false;
        }

        $expression = Syntax::line($expression);
        
        $startCode = 'if (' . $expression . ') {';
        $endCode = '}';
        
        Helpers::before($element, $startCode);
        Helpers::after($element, $endCode);
        
        $element->removeAttribute('tal:condition');

        return false;
    }
}