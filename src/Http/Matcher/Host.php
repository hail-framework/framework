<?php

namespace Hail\Http\Matcher;

use Psr\Http\Message\RequestInterface;

class Host implements MatcherInterface
{
    use NegativeResultTrait;
    use RegexTrait;

    private $host;

    /**
     * @param string $host
     */
    public function __construct(string $host)
    {
        $this->host = $this->getValue($host);
        $this->split($this->host, '.', true);
    }

    /**
     * {@inheritdoc}
     */
    public function matches(RequestInterface $request): bool
    {
        $host = $request->getUri()->getHost();

        return ($host === $this->host || $this->regex($host)) === $this->result;
    }
}