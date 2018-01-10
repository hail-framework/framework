<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\Message\AuthenticationInterface;
use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Send an authenticated request.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class AuthenticationPlugin implements PluginInterface
{
    /**
     * @var AuthenticationInterface An authentication system
     */
    private $authentication;

    /**
     * @param AuthenticationInterface $authentication
     */
    public function __construct(AuthenticationInterface $authentication)
    {
        $this->authentication = $authentication;
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        $request = $this->authentication->authenticate($request);

        return $handler->handle($request);
    }
}
