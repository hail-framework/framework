<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\Exception\TransferException;
use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Hail\Http\Client\Message\Cookie;
use Hail\Http\Client\Message\CookieJar;
use Hail\Http\Client\Message\CookieUtil;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Handle request cookies.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class CookiePlugin implements PluginInterface
{
    /**
     * Cookie storage.
     *
     * @var CookieJar
     */
    private $cookieJar;

    /**
     * @param CookieJar $cookieJar
     */
    public function __construct(CookieJar $cookieJar)
    {
        $this->cookieJar = $cookieJar;
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        $cookies = [];
        foreach ($this->cookieJar->getCookies() as $cookie) {
            if ($cookie->isExpired()) {
                continue;
            }

            if (!$cookie->matchDomain($request->getUri()->getHost())) {
                continue;
            }

            if (!$cookie->matchPath($request->getUri()->getPath())) {
                continue;
            }

            if ($cookie->isSecure() && ('https' !== $request->getUri()->getScheme())) {
                continue;
            }

            $cookies[] = \sprintf('%s=%s', $cookie->getName(), $cookie->getValue());
        }

        if (!empty($cookies)) {
            $request = $request->withAddedHeader('Cookie', \implode('; ', \array_unique($cookies)));
        }


        return $handler->handle($request)->then(function (ResponseInterface $response) use ($request) {
            if ($response->hasHeader('Set-Cookie')) {
                $setCookies = $response->getHeader('Set-Cookie');

                foreach ($setCookies as $setCookie) {
                    $cookie = $this->createCookie($request, $setCookie);

                    // Cookie invalid do not use it
                    if (null === $cookie) {
                        continue;
                    }

                    // Restrict setting cookie from another domain
                    if (!\preg_match("/\.{$cookie->getDomain()}$/", '.'.$request->getUri()->getHost())) {
                        continue;
                    }

                    $this->cookieJar->addCookie($cookie);
                }
            }

            return $response;
        });
    }

    /**
     * Creates a cookie from a string.
     *
     * @param RequestInterface $request
     * @param $setCookie
     *
     * @return Cookie|null
     *
     * @throws TransferException
     */
    private function createCookie(RequestInterface $request, $setCookie)
    {
        $parts = \explode(';', $setCookie);

        if (empty($parts) || !\strpos($parts[0], '=')) {
            return null;
        }

        list($name, $cookieValue) = $this->createValueKey($parts[0]);
        unset($parts[0]);

        $maxAge = null;
        $expires = null;
        $domain = $request->getUri()->getHost();
        $path = $request->getUri()->getPath();
        $secure = false;
        $httpOnly = false;

        // Add the cookie pieces into the parsed data array
        foreach ($parts as $part) {
            list($key, $value) = $this->createValueKey($part);

            switch (\strtolower($key)) {
                case 'expires':
                    try {
                        $expires = CookieUtil::parseDate($value);
                    } catch (\UnexpectedValueException $e) {
                        throw new TransferException("Cookie header `$name` expires value `$value` could not be converted to date", null, $e);
                    }

                    break;

                case 'max-age':
                    $maxAge = (int) $value;

                    break;

                case 'domain':
                    $domain = $value;

                    break;

                case 'path':
                    $path = $value;

                    break;

                case 'secure':
                    $secure = true;

                    break;

                case 'httponly':
                    $httpOnly = true;

                    break;
            }
        }

        return new Cookie($name, $cookieValue, $maxAge, $domain, $path, $secure, $httpOnly, $expires);
    }

    /**
     * Separates key/value pair from cookie.
     *
     * @param $part
     *
     * @return array
     */
    private function createValueKey($part)
    {
        $parts = \explode('=', $part, 2);
        $key = \trim($parts[0]);
        $value = isset($parts[1]) ? \trim($parts[1]) : true;

        return [$key, $value];
    }
}
