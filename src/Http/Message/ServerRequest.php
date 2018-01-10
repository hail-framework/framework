<?php

declare(strict_types=1);

namespace Hail\Http\Message;

use Hail\Http\Helpers;
use InvalidArgumentException;
use Psr\Http\Message\{
    ServerRequestInterface,
    StreamInterface,
    UploadedFileInterface,
    UriInterface
};

/**
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $cookieParams;

    /**
     * @var null|array|object
     */
    private $parsedBody;

    /**
     * @var array
     */
    private $queryParams;

    /**
     * @var array
     */
    private $serverParams;

    /**
     * @var UploadedFileInterface[]
     */
    private $uploadedFiles = [];

    /**
     * @param string                               $method        HTTP method
     * @param string|UriInterface                  $uri           URI
     * @param array                                $headers       Request headers
     * @param string|null|resource|StreamInterface $body          Request body
     * @param string                               $version       Protocol version
     * @param array                                $serverParams  Typically the $_SERVER superglobal
     * @param array                                $cookies       Cookies for the message, if any.
     * @param array                                $queryParams   Query params for the message, if any.
     * @param array                                $parsedBody    The deserialized body parameters, if any.
     * @param array                                $uploadedFiles Upload file information, a tree of UploadedFiles
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = 'php://input',
        string $version = '1.1',
        array $serverParams = [],
        array $cookies = [],
        array $queryParams = [],
        array $parsedBody = [],
        array $uploadedFiles = []
    ) {
        $this->serverParams = $serverParams;
        $this->cookieParams = $cookies;
        $this->queryParams = $queryParams;
        $this->parsedBody = $parsedBody;
        $this->uploadedFiles = $uploadedFiles;

        if ($body === 'php://input') {
            $body = new PhpInputStream();
        }

        parent::__construct($method, $uri, $headers, $body, $version);
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookieParams = $cookies;

        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->queryParams = $query;

        return $new;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->parsedBody = $data;

        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($attribute, $default = null)
    {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        }

        return $default;
    }

    public function withAttribute($attribute, $value)
    {
        $new = clone $this;
        $new->attributes[$attribute] = $value;

        return $new;
    }

    public function withoutAttribute($attribute)
    {
        if (!\array_key_exists($attribute, $this->attributes)) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$attribute]);

        return $new;
    }
}
