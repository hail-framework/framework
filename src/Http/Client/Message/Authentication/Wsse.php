<?php

namespace Hail\Http\Client\Message\Authentication;

use Hail\Http\Client\Message\AuthenticationInterface;
use Hail\Util\Generators;
use Psr\Http\Message\RequestInterface;

/**
 * Authenticate a PSR-7 Request using WSSE.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
final class Wsse implements AuthenticationInterface
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
        $nonce = \substr(\md5(Generators::unique()), 0, 16);
        $created = \date('c');
        $digest = \base64_encode(\sha1(\base64_decode($nonce) . $created . $this->password, true));

        $wsse = \sprintf(
            'UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"',
            $this->username,
            $digest,
            $nonce,
            $created
        );

        return $request
            ->withHeader('Authorization', 'WSSE profile="UsernameToken"')
            ->withHeader('X-WSSE', $wsse);
    }
}
