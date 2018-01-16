<?php

declare(strict_types=1);

namespace Hail\Http;

use Hail\Util\{
    Arrays, Strings
};
use Psr\Http\Message\{
    ServerRequestInterface, UploadedFileInterface, UriInterface
};

/**
 * ServerRequest wrapper
 *
 * @package Hail\Http
 *
 * @author  Feng Hao <flyinghail@msn.com>
 */
class Request
{
    /**
     * @var ServerRequestInterface
     */
    protected $serverRequest;

    /**
     * @var array
     */
    protected $input = [];

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * $this->input fill all params?
     *
     * @var bool
     */
    protected $all = false;

    public function __construct(ServerRequestInterface $serverRequest = null)
    {
        if ($serverRequest !== null) {
            $this->serverRequest = $serverRequest;
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest
     */
    public function setServerRequest(ServerRequestInterface $serverRequest): void
    {
        if ($this->serverRequest === $serverRequest) {
            return;
        }

        $this->serverRequest = $serverRequest;

        $this->input = $this->cache = [];
        $this->all = false;
    }

    /**
     * @return string
     */
    public function protocol(): string
    {
        return $this->serverRequest->getProtocolVersion();
    }

    /**
     * @return string
     */
    public function method(): string
    {
        return $this->serverRequest->getMethod();
    }

    /**
     * @return string
     */
    public function target(): string
    {
        return $this->serverRequest->getRequestTarget();
    }

    /**
     * @return UriInterface
     */
    public function uri(): UriInterface
    {
        return $this->serverRequest->getUri();
    }

    /**
     * @param array|null $values
     *
     * @return array
     */
    public function inputs(array $values = null): array
    {
        if ($values === null) {
            if ($this->all) {
                return $this->input;
            }

            $values = [];
            if ($this->serverRequest->getMethod() !== 'GET') {
                $values = (array) $this->serverRequest->getParsedBody();
            }

            $values += $this->serverRequest->getQueryParams();
            $this->all = true;
        }

        return $this->input = $values;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed
     */
    public function input(string $name, $value = null)
    {
        if ($value !== null) {
            !$this->all && $this->inputs();

            Arrays::set($this->input, $name, $value);
            $this->cache = [];

            return $value;
        }

        if (isset($this->input[$name])) {
            return $this->input[$name];
        }

        if (\array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        if ($this->all) {
            $found = Arrays::get($this->input, $name);
        } else {
            if ($this->serverRequest->getMethod() !== 'GET') {
                $found = $this->request($name);
            }

            $found = $found ?? $this->query($name);
        }

        return $this->cache[$name] = $found;
    }

    /**
     * Delete from input
     *
     * @param string $name
     */
    public function delete(string $name): void
    {
        !$this->all && $this->inputs();

        Arrays::delete($this->input, $name);
        $this->cache = [];
    }

    public function request(string $name = null)
    {
        $array = $this->serverRequest->getParsedBody();

        return $array[$name] ?? Arrays::get($array, $name);
    }

    public function query(string $name = null)
    {
        $array = $this->serverRequest->getQueryParams();

        return $array[$name] ?? Arrays::get($array, $name);
    }

    /**
     * @return array
     */
    public function files(): array
    {
        return $this->serverRequest->getUploadedFiles();
    }

    /**
     * @param string $name
     *
     * @return null|UploadedFileInterface
     */
    public function file(string $name): ?UploadedFileInterface
    {
        $array = $this->serverRequest->getUploadedFiles();

        return $array[$name] ?? Arrays::get($array, $name);
    }

    /**
     * @param string $name
     *
     * @return null|string
     */
    public function cookie(string $name): ?string
    {
        return $this->serverRequest->getCookieParams()[$name] ?? null;
    }

    /**
     * @param string $name
     *
     * @return null|string
     */
    public function server(string $name): ?string
    {
        return $this->serverRequest->getServerParams()[$name] ?? null;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function attribute(string $name)
    {
        return $this->serverRequest->getAttribute($name);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function header(string $name): string
    {
        return $this->serverRequest->getHeaderLine($name);
    }

    /**
     * @return bool
     */
    public function secure(): bool
    {
        return $this->serverRequest->getUri()->getScheme() === 'https';
    }

    /**
     * Is AJAX request?
     *
     * @return bool
     */
    public function ajax(): bool
    {
        return $this->serverRequest->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Determine if the request is the result of an PJAX call.
     *
     * @return bool
     */
    public function pjax(): bool
    {
        return $this->serverRequest->getHeaderLine('X-PJAX') === 'true';
    }

    /**
     * Determine if the request is sending JSON.
     *
     * @return bool
     */
    public function json(): bool
    {
        return Strings::contains(
            $this->serverRequest->getHeaderLine('Content-Type') ?? '', ['/json', '+json']
        );
    }

    /**
     * Determine if the current request probably expects a JSON response.
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        return ($this->ajax() && !$this->pjax()) || $this->wantsJson();
    }

    /**
     * Determine if the current request is asking for JSON in return.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->serverRequest->getHeaderLine('Accept');

        return $acceptable !== null && Strings::contains($acceptable, ['/json', '+json']);
    }
}