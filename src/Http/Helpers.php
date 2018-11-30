<?php

namespace Hail\Http;

use Hail\Http\Message\ServerRequest;
use Hail\Http\Message\UploadedFile;
use Hail\Http\Message\Uri;
use InvalidArgumentException;
use Psr\Http\Message\{
    MessageInterface,
    ServerRequestInterface,
    StreamInterface,
    UploadedFileInterface
};

/**
 * Class Helpers
 *
 * @package Hail\Http
 */
class Helpers
{
    protected static $methods = [
        'HEAD' => 'HEAD',
        'GET' => 'GET',
        'POST' => 'POST',
        'PUT' => 'PUT',
        'PATCH' => 'PATCH',
        'DELETE' => 'DELETE',
        'PURGE' => 'PURGE',
        'OPTIONS' => 'OPTIONS',
        'TRACE' => 'TRACE',
        'CONNECT' => 'CONNECT',
    ];

    protected static $headerNames = [
        'ETag',
        'TE',
        'X-PJAX',
        'WWW-Authenticate',
        'MIME-Version',
        'Content-MD5',
        'X-OperaMini-Phone-UA',
        'X-UCBrowser-Device-UA',
        'X-Bolt-Phone-UA',
        'Device-Stock-UA',
        'CloudFront-Is-Desktop-Viewer',
        'CloudFront-Is-Mobile-Viewer',
        'CloudFront-Is-SmartTV-Viewer',
        'CloudFront-Is-Tablet-Viewer',
    ];

    protected static $realHeaderNames;

    protected static function getRealHeaderNames(): array
    {
        if (static::$realHeaderNames === null) {
            $names = [];
            foreach (static::$headerNames as $v) {
                $names[\strtolower($v)] = $v;
            }

            static::$realHeaderNames = $names;
        }

        return static::$realHeaderNames;
    }

    public static function normalizeHeaderName(string $header): string
    {
        if (\strpos($header, '_') !== false) {
            $header = \str_replace('_', '-', $header);
        }

        $header = \strtolower(\trim($header));

        $names = static::getRealHeaderNames();
        if (isset($names[$header])) {
            return $names[$header];
        }

        return \ucwords($header, '-');
    }

    public static function getHeaders(array $server = null)
    {
        if ($server === null) {
            if (\function_exists('\getallheaders')) {
                return \getallheaders();
            }

            $server = $_SERVER;
        }

        $headers = [];
        $serverMap = [
            'CONTENT_TYPE' => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5' => 'Content-Md5',
        ];

        foreach ($server as $key => $value) {
            if (\strpos($key, 'HTTP_') === 0) {
                $key = \substr($key, 5);
                if (!isset($serverMap[$key], $server[$key])) {
                    $key = self::normalizeHeaderName($key);
                    $headers[$key] = $value;
                }
            } elseif (isset($serverMap[$key])) {
                $headers[$serverMap[$key]] = $value;
            }
        }

        if (!isset($headers['Authorization'])) {
            if (isset($server['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($server['PHP_AUTH_USER'])) {
                $headers['Authorization'] = 'Basic ' . \base64_encode($server['PHP_AUTH_USER'] . ':' . $server['PHP_AUTH_PW'] ?? '');
            } elseif (isset($server['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $server['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }

    /**
     * Parse a cookie header according to RFC 6265.
     *
     * PHP will replace special characters in cookie names, which results in other cookies not being available due to
     * overwriting. Thus, the server request should take the cookies from the request header instead.
     *
     * @param string $cookieHeader
     *
     * @return array
     */
    public static function parseCookieHeader(string $cookieHeader)
    {
        \preg_match_all('(
            (?:^\\n?[ \t]*|;[ ])
            (?P<name>[!#$%&\'*+-.0-9A-Z^_`a-z|~]+)
            =
            (?P<DQUOTE>"?)
                (?P<value>[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)
            (?P=DQUOTE)
            (?=\\n?[ \t]*$|;[ ])
        )x', $cookieHeader, $matches, PREG_SET_ORDER);

        $cookies = [];

        foreach ($matches as $match) {
            $cookies[$match['name']] = \urldecode($match['value']);
        }

        return $cookies;
    }

    /**
     * Trims whitespace from the header values.
     *
     * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
     *
     * header-field = field-name ":" OWS field-value OWS
     * OWS          = *( SP / HTAB )
     *
     * @param string[] $values Header values
     *
     * @return string[] Trimmed header values
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    public static function trimHeaderValues(array $values): array
    {
        foreach ($values as &$v) {
            $v = \trim($v, " \t");
        }

        return $values;
    }

    /**
     * Create a URI string from its various parts.
     *
     * @param string $scheme
     * @param string $authority
     * @param string $path
     * @param string $query
     * @param string $fragment
     *
     * @return string
     */
    public static function createUriString(
        string $scheme,
        string $authority,
        string $path,
        string $query = '',
        string $fragment = ''
    ): string {
        $uri = '';

        if ($scheme !== '') {
            $uri .= $scheme . ':';
        }

        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        if ($path !== '') {
            if ($path[0] !== '/') {
                if ($authority !== '') {
                    // If the path is rootless and an authority is present, the path MUST be prefixed by "/"
                    $path = '/' . $path;
                }
            } elseif (isset($path[1]) && $path[1] === '/') {
                if ($authority === '') {
                    // If the path is starting with more than one "/" and no authority is present, the
                    // starting slashes MUST be reduced to one.
                    $path = '/' . \ltrim($path, '/');
                }
            }

            $uri .= $path;
        }

        if ($query !== '') {
            $uri .= '?' . $query;
        }

        if ($fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * Get method from server variables.
     *
     * @param array $server Typically $_SERVER or similar structure.
     *
     * @return string
     */
    public static function getMethod(array $server): string
    {
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' &&
            isset(
                $server['HTTP_X_HTTP_METHOD_OVERRIDE'],
                self::$methods[$server['HTTP_X_HTTP_METHOD_OVERRIDE']]
            )
        ) {
            $method = $server['HTTP_X_HTTP_METHOD_OVERRIDE'];
        }

        return $method;
    }

    /**
     * Get protocol from server variables.
     *
     * @param array $server Typically $_SERVER or similar structure.
     *
     * @return string
     * @throws \UnexpectedValueException
     */
    public static function getProtocol(array $server): string
    {
        if (!isset($server['SERVER_PROTOCOL'])) {
            return '1.1';
        }

        if (!\preg_match('#^(HTTP/)?(?P<version>[1-9]\d*(?:\.\d)?)$#', $server['SERVER_PROTOCOL'], $matches)) {
            throw new \UnexpectedValueException("Unrecognized protocol version ({$server['SERVER_PROTOCOL']})");
        }

        return $matches['version'];
    }

    /**
     * Copy the contents of a stream into another stream until the given number
     * of bytes have been read.
     *
     * @author Michael Dowling and contributors to guzzlehttp/psr7
     *
     * @param StreamInterface $source Stream to read from
     * @param StreamInterface $dest   Stream to write to
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream
     *
     * @throws \RuntimeException on error
     */
    public static function copyToStream(StreamInterface $source, StreamInterface $dest, $maxLen = -1)
    {
        if ($maxLen === -1) {
            while (!$source->eof()) {
                if (!$dest->write($source->read(1048576))) {
                    break;
                }
            }

            return;
        }

        $bytes = 0;
        while (!$source->eof()) {
            $buf = $source->read($maxLen - $bytes);
            if (!($len = \strlen($buf))) {
                break;
            }
            $bytes += $len;
            $dest->write($buf);
            if ($bytes === $maxLen) {
                break;
            }
        }
    }

    /**
     * Add or remove the Content-Length header
     * Used by middlewares that modify the body content
     *
     * @param MessageInterface $response
     *
     * @return MessageInterface
     */
    public static function fixContentLength(MessageInterface $response): MessageInterface
    {
        $size = $response->getBody()->getSize();
        if ($size !== null) {
            return $response->withHeader('Content-Length', (string) $size);
        }

        return $response->withoutHeader('Content-Length');
    }

    /**
     * Marshal the host and port from HTTP headers and/or the PHP environment
     *
     * @param array $server
     *
     * @return array
     */
    public static function getHostAndPortFromArray(array $server): array
    {
        if (isset($server['HTTP_HOST'])) {
            return self::getHostAndPortFromHost($server['HTTP_HOST']);
        }

        if (!isset($server['SERVER_NAME'])) {
            return ['', null];
        }

        $host = $server['SERVER_NAME'];
        $port = null;
        if (isset($server['SERVER_PORT'])) {
            $port = (int) $server['SERVER_PORT'];
        }

        // Misinterpreted IPv6-Address
        // Reported for Safari on Windows
        if (isset($server['SERVER_ADDR']) && \preg_match('/^\[[0-9a-fA-F\:]+\]$/', $host)) {
            $host = '[' . $server['SERVER_ADDR'] . ']';
            $port = $port ?: 80;
            if ($port . ']' === \substr($host, \strrpos($host, ':') + 1)) {
                // The last digit of the IPv6-Address has been taken as port
                // Unset the port so the default port can be used
                $port = null;
            }
        }

        return [$host, $port];
    }

    /**
     * Marshal the host and port from the request header
     *
     * @param string|array $host
     *
     * @return array
     */
    private static function getHostAndPortFromHost($host): array
    {
        if (\is_array($host)) {
            $host = \implode(', ', $host);
        }

        $port = null;

        // works for regname, IPv4 & IPv6
        if (\preg_match('|\:(\d+)$|', $host, $matches)) {
            $host = \substr($host, 0, -1 * (\strlen($matches[1]) + 1));
            $port = (int) $matches[1];
        }

        return [$host, $port];
    }

    /**
     * Detect the base URI for the request
     *
     * Looks at a variety of criteria in order to attempt to autodetect a base
     * URI, including rewrite URIs, proxy URIs, etc.
     *
     * From ZF2's Zend\Http\PhpEnvironment\Request class
     *
     * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
     * @license   http://framework.zend.com/license/new-bsd New BSD License
     *
     * @param array $server
     *
     * @return string
     */
    public static function getRequestUri(array $server)
    {
        // IIS7 with URL Rewrite: make sure we get the unencoded url
        // (double slash problem).
        $iisUrlRewritten = $server['IIS_WasUrlRewritten'] ?? null;
        $unencodedUrl = $server['UNENCODED_URL'] ?? null;
        if ('1' === $iisUrlRewritten && !empty($unencodedUrl)) {
            return $unencodedUrl;
        }

        $requestUri = $server['REQUEST_URI'] ?? null;

        // Check this first so IIS will catch.
        $httpXRewriteUrl = $server['HTTP_X_REWRITE_URL'] ?? null;
        if ($httpXRewriteUrl !== null) {
            $requestUri = $httpXRewriteUrl;
        }

        // Check for IIS 7.0 or later with ISAPI_Rewrite
        $httpXOriginalUrl = $server['HTTP_X_ORIGINAL_URL'] ?? null;
        if ($httpXOriginalUrl !== null) {
            $requestUri = $httpXOriginalUrl;
        }

        if ($requestUri !== null) {
            return \preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
        }

        $origPathInfo = $server['ORIG_PATH_INFO'] ?? null;
        if (empty($origPathInfo)) {
            return '/';
        }

        return $origPathInfo;
    }

    /**
     * @param array|null $server
     * @param array|null $query
     * @param array|null $body
     * @param array|null $cookies
     * @param array|null $files
     *
     * @return ServerRequestInterface
     */
    public static function createServer(
        array $server = null,
        array $query = null,
        array $body = null,
        array $cookies = null,
        array $files = null
    ): ServerRequestInterface {
        $server = static::normalizeServer($server ?: $_SERVER);
        $files = static::normalizeFiles($files ?: $_FILES);

        $method = self::getMethod($server);
        $headers = self::getHeaders($server);

        if (!isset($server['HTTPS'])) {
            $server['HTTPS'] = 'off';
        }

        if (
            $server['HTTPS'] === 'off' &&
            isset($headers['X-Forwarded-Proto']) &&
            $headers['X-Forwarded-Proto'] === 'https'
        ) {
            $server['HTTPS'] = 'on';
        }

        $uri = Uri::fromArray($server);

        $protocol = self::getProtocol($server);

        if ($cookies === null && isset($headers['Cookie'])) {
            $cookies = self::parseCookieHeader($headers['Cookie']);
        }

        return new ServerRequest($method, $uri, $headers, 'php://input', $protocol, $server, $cookies ?: $_COOKIE,
            $query ?: $_GET, $body ?: $_POST, $files);
    }

    /**
     * Marshal the $_SERVER array
     *
     * Pre-processes and returns the $_SERVER superglobal.
     *
     * @param array $server
     *
     * @return array
     */
    public static function normalizeServer(array $server)
    {
        // This seems to be the only way to get the Authorization header on Apache
        if (
            isset($server['HTTP_AUTHORIZATION']) ||
            !\function_exists('\apache_request_headers')
        ) {
            return $server;
        }

        $apacheRequestHeaders = \apache_request_headers();

        if (
            isset($apacheRequestHeaders[$name = 'Authorization']) ||
            isset($apacheRequestHeaders[$name = 'authorization'])
        ) {
            $server['HTTP_AUTHORIZATION'] = $apacheRequestHeaders[$name];

            return $server;
        }

        return $server;
    }

    /**
     * Return an UploadedFile instance array.
     *
     * @param array $files A array which respect $_FILES structure
     *
     * @throws InvalidArgumentException for unrecognized values
     *
     * @return array
     */
    public static function normalizeFiles(array $files)
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (\is_array($value)) {
                if (isset($value['tmp_name'])) {
                    $normalized[$key] = self::createUploadedFileFromSpec($value);
                } else {
                    $normalized[$key] = self::normalizeFiles($value);
                }
            } else {
                throw new InvalidArgumentException('Invalid value in files specification');
            }
        }

        return $normalized;
    }

    /**
     * Create and return an UploadedFile instance from a $_FILES specification.
     *
     * If the specification represents an array of values, this method will
     * delegate to normalizeNestedFileSpec() and return that return value.
     *
     * @param array $value $_FILES struct
     *
     * @return array|UploadedFileInterface
     * @throws InvalidArgumentException
     */
    private static function createUploadedFileFromSpec(array $value)
    {
        if (\is_array($value['tmp_name'])) {
            return self::normalizeNestedFileSpec($value);
        }

        return new UploadedFile(
            $value['tmp_name'],
            (int) $value['size'],
            (int) $value['error'],
            $value['name'],
            $value['type']
        );
    }

    /**
     * Normalize an array of file specifications.
     *
     * Loops through all nested files and returns a normalized array of
     * UploadedFileInterface instances.
     *
     * @param array $files
     *
     * @return UploadedFileInterface[]
     * @throws InvalidArgumentException
     */
    private static function normalizeNestedFileSpec(array $files = [])
    {
        $normalizedFiles = [];

        foreach ($files['tmp_name'] as $key => $v) {
            $spec = [
                'tmp_name' => $v,
                'size' => $files['size'][$key],
                'error' => $files['error'][$key],
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
            ];
            $normalizedFiles[$key] = self::createUploadedFileFromSpec($spec);
        }

        return $normalizedFiles;
    }

    /**
     * @param string $value
     *
     * @return array
     */
    public static function parseHeaderValue(string $value): array
    {
        $parts = \explode(';', $value);
        $type = \trim($parts[0]);
        unset($parts[0]);

        $parameters = [];
        foreach ($parts as $part) {
            [$k, $v] = \explode('=', $part, 2);

            $key = \strtolower(\trim($k));
            $parameters[$key] = \trim($v, ' "');
        }

        return [$type, $parameters];
    }
}