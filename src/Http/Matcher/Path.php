<?php

namespace Hail\Http\Matcher;

use Psr\Http\Message\RequestInterface;

class Path implements MatcherInterface
{
    use NegativeResultTrait;
    use RegexTrait;

    private $path;

    /**
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->path = \rtrim($this->getValue($path), '/');
        $this->split($this->path, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function matches(RequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return (
                ($path === $this->path) ||
                \stripos($path, $this->path . '/') === 0 ||
                $this->regex($path)
            ) === $this->result;
    }
}