<?php

namespace Hail\Http\Client\Message\Authentication;

use Hail\Http\Client\Message\AuthenticationInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Authenticate a PSR-7 Request by adding parameters to its query.
 *
 * Note: Although in some cases it can be useful, we do not recommend using query parameters for authentication.
 * Credentials in the URL is generally unsafe as they are not encrypted, anyone can see them.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
final class QueryParam implements AuthenticationInterface
{
    /**
     * @var array
     */
    private $params = [];

    /**
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        $query = $uri->getQuery();
        $params = [];

        \parse_str($query, $params);

        $params = \array_merge($params, $this->params);

        $query = \http_build_query($params);

        $uri = $uri->withQuery($query);

        return $request->withUri($uri);
    }
}
