<?php

namespace Hail\Http\Matcher;

use Psr\Http\Message\RequestInterface;

class Methods implements MatcherInterface
{
    use NegativeResultTrait;

    private $methods = [];

    /**
     * @param string|array $methods
     */
    public function __construct($methods)
    {
        $methods = (array) $methods;

        foreach ($methods as $method) {
            $this->methods[\strtoupper($this->getValue($method))] = $this->result;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function matches(RequestInterface $request): bool
    {
        $method = $request->getMethod();

        $match = true;

        foreach ($this->methods as $check => $result) {
            $match = $match && ($method === $check) === $result;
        }

        return $match;
    }
}