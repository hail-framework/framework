<?php
namespace Hail\Http\Matcher;

use Psr\Http\Message\RequestInterface;

interface MatcherInterface
{
    /**
     * Evaluate if the request matches with the condition
     *
     * @param RequestInterface $request
     *
     * @return bool
     */
    public function matches(RequestInterface $request): bool;
}