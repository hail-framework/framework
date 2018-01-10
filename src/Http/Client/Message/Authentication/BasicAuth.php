<?php

namespace Hail\Http\Client\Message\Authentication;

use Hail\Http\Client\Message\AuthenticationInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Authenticate a PSR-7 Request using Basic Auth.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
final class BasicAuth implements AuthenticationInterface
{
    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @param string $username
     * @param string $password
     */
    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', 'Basic ' . \base64_encode($this->username . ':' . $this->password));
    }
}
