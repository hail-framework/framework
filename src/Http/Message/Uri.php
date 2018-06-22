<?php

declare(strict_types=1);

namespace Hail\Http\Message;

use Hail\Http\Helpers;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 URI implementation.
 *
 * @author Michael Dowling
 * @author Tobias Schultze
 * @author Matthew Weier O'Phinney
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Uri implements UriInterface
{
    /**
     * Sub-delimiters used in user info, query strings and fragments.
     *
     * @const string
     */
    private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * Unreserved characters used in user info, paths, query strings, and fragments.
     *
     * @const string
     */
    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~\pL';

    private static $schemes = [
        'http' => 80,
        'https' => 443,
    ];

    /** @var string Uri scheme. */
    private $scheme = '';

    /** @var string Uri user info. */
    private $userInfo = '';

    /** @var string Uri host. */
    private $host = '';

    /** @var int|null Uri port. */
    private $port;

    /** @var string Uri path. */
    private $path = '';

    /** @var string Uri query string. */
    private $query = '';

    /** @var string Uri fragment. */
    private $fragment = '';

    /**
     * @param string $uri
     */
    public function __construct(string $uri = '')
    {
        if ($uri) {
            $this->parseUri($uri);
        }
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return Helpers::createUriString(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme($scheme): self
    {
        $scheme = $this->filterScheme($scheme);

        if ($this->scheme === $scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;
        $new->port = $new->filterPort($new->port);

        return $new;
    }

    public function withUserInfo($user, $password = null): self
    {
        $info = $this->filterUserInfo($user);
        if ($password !== null && $password !== '') {
            $info .= ':' . $this->filterUserInfo($password);
        }

        if ($this->userInfo === $info) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $info;

        return $new;
    }

    public function withHost($host): self
    {
        $host = \strtolower($host);

        if ($this->host === $host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;

        return $new;
    }

    public function withPort($port): self
    {
        $port = $this->filterPort($port);

        if ($this->port === $port) {
            return $this;
        }

        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new \InvalidArgumentException('Invalid port "' . $port . '" specified; must be a valid TCP/UDP port');
        }

        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    public function withPath($path): self
    {
        if (\strpos($path, '?') !== false) {
            throw new \InvalidArgumentException(
                'Invalid path provided; must not contain a query string'
            );
        }

        if (\strpos($path, '#') !== false) {
            throw new \InvalidArgumentException(
                'Invalid path provided; must not contain a URI fragment'
            );
        }

        $path = $this->filterPath($path);

        if ($this->path === $path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    public function withQuery($query): self
    {
        if (\strpos($query, '#') !== false) {
            throw new \InvalidArgumentException(
                'Query string must not include a URI fragment'
            );
        }


        $query = $this->filterQuery($query);

        if ($this->query === $query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    public function withFragment($fragment): self
    {
        $fragment = $this->filterFragment($fragment);

        if ($this->fragment === $fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    /**
     * Parse a URI into its parts, and set the properties
     *
     * @param string $uri
     *
     * @throws \InvalidArgumentException
     */
    private function parseUri(string $uri)
    {
        $parts = \parse_url($uri);

        if (false === $parts) {
            throw new \InvalidArgumentException(
                'The source URI string appears to be malformed'
            );
        }

        $this->scheme = isset($parts['scheme']) ? $this->filterScheme($parts['scheme']) : '';
        $this->userInfo = isset($parts['user']) ? $this->filterUserInfo($parts['user']) : '';
        $this->host = isset($parts['host']) ? \strtolower($parts['host']) : '';
        $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
        $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->filterQuery($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->filterFragment($parts['fragment']) : '';
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $this->filterUserInfo($parts['pass']);
        }
    }

    /**
     * Filters the scheme to ensure it is a valid scheme.
     *
     * @param string $scheme Scheme name.
     *
     * @return string Filtered scheme.
     * @throws \InvalidArgumentException
     */
    private function filterScheme($scheme)
    {
        $scheme = \strtolower($scheme);
        $scheme = \preg_replace('#:(//)?$#', '', $scheme);

        if ('' === $scheme) {
            return '';
        }

        if (!isset(static::$schemes[$scheme])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported scheme "%s"; must be any empty string or in the set (%s)',
                $scheme,
                \implode(', ', \array_keys(static::$schemes))
            ));
        }

        return $scheme;
    }

    /**
     * @param int|null $port
     *
     * @throws \InvalidArgumentException If the port is invalid
     *
     * @return int|null
     */
    private function filterPort(?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        $port = (int) $port;
        if (1 > $port || 0xffff < $port) {
            throw new \InvalidArgumentException(sprintf('Invalid port: %d. Must be between 1 and 65535', $port));
        }

        return
            !isset(self::$schemes[$this->scheme]) ||
            $port !== self::$schemes[$this->scheme]
                ? $port : null;
    }

    /**
     * Filters a part of user info in a URI to ensure it is properly encoded.
     *
     * @param string $part
     *
     * @return string
     */
    private function filterUserInfo(string $part): string
    {
        // Note the addition of `%` to initial charset; this allows `|` portion
        // to match and thus prevent double-encoding.
        return \preg_replace_callback(
            '/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ']+|%(?![A-Fa-f0-9]{2}))/u',
            [$this, 'urlEncodeChar'],
            $part
        );
    }

    /**
     * Filters the path of a URI to ensure it is properly encoded.
     *
     * @param string $path
     *
     * @return string
     */
    private function filterPath(string $path): string
    {
        $path = \preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . ')(:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/u',
            [$this, 'urlEncodeChar'],
            $path
        );

        if ('' === $path) {
            // No path
            return $path;
        }

        if ($path[0] !== '/') {
            // Relative path
            return $path;
        }

        // Ensure only one leading slash, to prevent XSS attempts.
        return '/' . \ltrim($path, '/');
    }

    /**
     * Filter a fragment value to ensure it is properly encoded.
     *
     * @param string $fragment
     *
     * @return string
     */
    private function filterFragment(string $fragment)
    {
        if ('' !== $fragment && \strpos($fragment, '#') === 0) {
            $fragment = '%23' . \substr($fragment, 1);
        }

        return $this->filterQueryOrFragment($fragment);
    }

    /**
     * Split a query value into a key/value tuple.
     *
     * @param string $value
     *
     * @return array A value with exactly two elements, key and value
     */
    private function splitQueryValue(string $value): array
    {
        $data = \explode('=', $value, 2);
        if (!isset($data[1])) {
            $data[] = null;
        }

        return $data;
    }

    /**
     * Filter a query string to ensure it is propertly encoded.
     *
     * Ensures that the values in the query string are properly urlencoded.
     *
     * @param string $query
     *
     * @return string
     */
    private function filterQuery(string $query): string
    {
        if ($query !== '' && $query[0] === '?') {
            $query = \substr($query, 1);
        }

        $parts = \explode('&', $query);
        foreach ($parts as $index => $part) {
            [$key, $value] = $this->splitQueryValue($part);
            if ($value === null) {
                $parts[$index] = $this->filterQueryOrFragment($key);
                continue;
            }

            $parts[$index] = $this->filterQueryOrFragment($key) . '=' . $this->filterQueryOrFragment($value);
        }

        return implode('&', $parts);
    }

    /**
     * Filters the query string or fragment of a URI.
     *
     * @param string $str
     *
     * @return string
     */
    private function filterQueryOrFragment(string $str): string
    {
        return \preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'urlEncodeChar'],
            $str
        );
    }

    private function urlEncodeChar(array $match): string
    {
        return \rawurlencode($match[0]);
    }

    /**
     * Get a Uri populated with values from server variables.
     *
     * @param array $server Typically $_SERVER or similar structure.
     *
     * @return UriInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $server)
    {
        $uri = new self('');

        if (isset($server['HTTPS'])) {
            $uri->scheme = $server['HTTPS'] === 'on' ? 'https' : 'http';
        }

        [$host, $port] = Helpers::getHostAndPortFromArray($server);

        $uri->host = \strtolower($host);
        $uri->port = $uri->filterPort($port);

        $path = Helpers::getRequestUri($server);

        $fragment = '';
        if (\strpos($path, '#') !== false) {
            [$path, $fragment] = \explode('#', $path, 2);
        }

        $uri->path = $uri->filterPath(\explode('?', $path, 2)[0]);
        $uri->fragment = $uri->filterFragment($fragment);

        if (isset($server['QUERY_STRING'])) {
            $uri->query = $uri->filterQuery($server['QUERY_STRING']);
        }

        return $uri;
    }
}
