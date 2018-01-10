<?php

namespace Hail\Http\Matcher;

use Psr\Http\Message\RequestInterface;

class Schemes implements MatcherInterface
{
    use NegativeResultTrait;

    private $schemes = [];

    /**
     * @param string|array $schemes
     */
    public function __construct($schemes)
    {
        $schemes = (array) $schemes;

        foreach ($schemes as $scheme) {
            $this->schemes[\strtolower($this->getValue($scheme))] = $this->result;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function matches(RequestInterface $request): bool
    {
        $scheme = $request->getUri()->getScheme();

        $match = true;

        foreach ($this->schemes as $check => $result) {
            $match = $match && ($scheme === $check) === $result;
        }

        return $match;
    }
}