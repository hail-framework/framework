<?php

namespace Hail\Template\Processor\Vue\Parser;

use Hail\Template\Expression\Expression;

class ParseResult
{
    /**
     * @var string[]
     */
    private $expressions;

    /**
     * @var array[]
     */
    private $filterCalls;

    /**
     * @param string[] $expressions
     * @param array[]  $filterCalls
     */
    public function __construct(array $expressions, array $filterCalls)
    {
        $this->expressions = $expressions;
        $this->filterCalls = $filterCalls;
    }

    /**
     * @return string[]
     */
    public function expressions(): array
    {
        return $this->expressions;
    }

    /**
     * @return string
     */
    public function toExpression(): string
    {
        if (\count($this->filterCalls) === 0) {
            return Expression::parse($this->expressions[0]);
        }

        $nextFilterArguments = $this->parseExpressions($this->expressions);

        $result = null;
        foreach ($this->filterCalls as [$filter, $arguments]) {
            $filerArguments = \array_merge(
                [\var_export($filter, true)],
                $nextFilterArguments,
                $this->parseExpressions($arguments)
            );


            $result = '$this->filter(' . implode(',', $filerArguments) . ')';
            $nextFilterArguments = [$result];
        }

        return $result;
    }

    private function parseExpressions(array $expressions): array
    {
        foreach ($expressions as &$exp) {
            $exp = Expression::parse($exp);
        }

        return $expressions;
    }

}
