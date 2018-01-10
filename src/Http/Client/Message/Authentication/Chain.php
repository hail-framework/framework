<?php

namespace Hail\Http\Client\Message\Authentication;

use Hail\Http\Client\Message\AuthenticationInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Authenticate a PSR-7 Request with a multiple authentication methods.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
final class Chain implements AuthenticationInterface
{
    /**
     * @var AuthenticationInterface[]
     */
    private $authenticationChain = [];

    /**
     * @param AuthenticationInterface[] $authenticationChain
     */
    public function __construct(array $authenticationChain = [])
    {
        foreach ($authenticationChain as $authentication) {
            if (!$authentication instanceof AuthenticationInterface) {
                throw new \InvalidArgumentException(
                    'Members of the authentication chain must be of type Http\Message\Authentication'
                );
            }
        }

        $this->authenticationChain = $authenticationChain;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        foreach ($this->authenticationChain as $authentication) {
            $request = $authentication->authenticate($request);
        }

        return $request;
    }
}
