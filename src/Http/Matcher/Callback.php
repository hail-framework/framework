<?php
namespace Hail\Http\Matcher;

use Psr\Http\Message\RequestInterface;

final class Callback implements MatcherInterface
{
    /**
     * @var callable
     */
    private $callback;

    /**
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(RequestInterface $request): bool
    {
        return (bool) ($this->callback)($request);
    }
}