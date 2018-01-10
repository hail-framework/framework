<?php

namespace Hail\Http\Client\Message\Authentication;

use Hail\Http\Client\Message\AuthenticationInterface;

use Hail\Http\Matcher\MatcherInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Authenticate a PSR-7 Request if the request is matching the given request matcher.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
final class RequestConditional implements AuthenticationInterface
{
    /**
     * @var MatcherInterface
     */
    private $requestMatcher;

    /**
     * @var AuthenticationInterface
     */
    private $authentication;

    /**
     * @param MatcherInterface        $requestMatcher
     * @param AuthenticationInterface $authentication
     */
    public function __construct(MatcherInterface $requestMatcher, AuthenticationInterface $authentication)
    {
        $this->requestMatcher = $requestMatcher;
        $this->authentication = $authentication;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        if ($this->requestMatcher->matches($request)) {
            return $this->authentication->authenticate($request);
        }

        return $request;
    }
}
