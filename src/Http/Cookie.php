<?php

namespace Hail\Http;

/**
 * Class SetCookie
 *
 * @package Hail
 */
class Cookie implements \IteratorAggregate, \Countable
{
    public const SAMESITE_LAX = 'lax';
    public const SAMESITE_STRICT = 'strict';

    public $prefix = '';
    public $domain = '';
    public $path = '/';
    public $secure = false;
    public $httpOnly = true;
    public $lifetime = 0;
    public $sameSite;

    /**
     * @var string[][][][]
     */
    protected $cookies = [];

    protected $injected = false;

    /**
     * Cookie constructor.
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config = [])
    {
        $sameSite = $config['sameSite'] ?? null;
        if (!\in_array($sameSite, [self::SAMESITE_LAX, self::SAMESITE_STRICT, null], true)) {
            throw new \InvalidArgumentException('The "sameSite" parameter value is not valid.');
        }

        $this->prefix = $config['prefix'] ?? '';
        $this->domain = $config['domain'] ?? '';
        $this->path = $config['path'] ?? '/';
        $this->secure = $config['secure'] ?? false;
        $this->httpOnly = $config['httponly'] ?? true;
        $this->lifetime = $config['lifetime'] ?? 0;
        $this->sameSite = $sameSite;
    }

    public function reset()
    {
        $this->cookies = [];
        $this->injected = false;
    }

    /**
     * @param string               $name
     * @param string               $value
     * @param string|int|\DateTime $time
     * @param string               $path
     * @param string               $domain
     * @param bool                 $secure
     * @param bool                 $httpOnly
     * @param string               $sameSite
     */
    public function set(
        string $name,
        string $value,
        $time = null,
        string $path = null,
        string $domain = null,
        bool $secure = null,
        bool $httpOnly = null,
        string $sameSite = null
    ): void {
        if ($this->injected) {
            throw new \LogicException('Can not set cookie after headers send');
        }

        $name = $this->prefix . $name;
        $path = $path ?? $this->path;
        $domain = $domain ?? $this->domain;

        $this->cookies[$domain][$path][$name] = [
            $name,
            $value,
            $time ?? $this->lifetime,
            $path,
            $domain,
            $secure ?? $this->secure,
            $httpOnly ?? $this->httpOnly,
            $sameSite ?? $this->sameSite,
        ];
    }

    /**
     * @param string $name
     * @param string $path
     * @param string $domain
     */
    public function delete(string $name, string $path = null, string $domain = null): void
    {
        $this->set($name, '', 0, $path, $domain);
    }

    /**
     * @param string $name
     * @param string $path
     * @param string $domain
     */
    public function remove(string $name, string $path = null, string $domain = null): void
    {
        $path = $path ?? $this->path;
        $domain = $domain ?? $this->domain;

        unset($this->cookies[$domain][$path][$name]);
        if (empty($this->cookies[$domain][$path])) {
            unset($this->cookies[$domain][$path]);
            if (empty($this->cookies[$domain])) {
                unset($this->cookies[$domain]);
            }
        }
    }

    public function inject(array &$headers, $send = true): void
    {
        if ($this->cookies !== []) {
            $headers['Set-Cookie'] = [];

            foreach ($this->cookies as $paths) {
                foreach ($paths as $cookies) {
                    foreach ($cookies as $cookie) {
                        $headers['Set-Cookie'][] = $this->headerValue($cookie);
                    }
                }
            }
        }

        if ($send) {
            $this->injected = true;
        }
    }

    protected function headerValue(array $cookie): string
    {
        [$name, $value, $time, $path, $domain, $secure, $httpOnly, $sameSite] = $cookie;

        $str = \urlencode($name) . '=';
        if ('' === $value) {
            $str .= 'deleted; expires=' . \gmdate('D, d-M-Y H:i:s T', \time() - 31536001) . '; max-age=-31536001';
        } else {
            $str .= \urlencode($value);
            if (0 !== $time) {
                $time = $this->getExpiresTime($time);
                $str .= '; expires=' . \gmdate('D, d-M-Y H:i:s T', $time) . '; max-age=' . ($time - \time());
            }
        }

        if ($path) {
            $str .= '; path=' . $path;
        }
        if ($domain) {
            $str .= '; domain=' . $domain;
        }
        if (true === $secure) {
            $str .= '; secure';
        }
        if (true === $httpOnly) {
            $str .= '; httponly';
        }
        if (null !== $sameSite) {
            $str .= '; samesite=' . $sameSite;
        }

        return $str;
    }

    /**
     * Convert to unix timestamp
     *
     * @param  string|int|\DateTimeInterface $time
     *
     * @return int
     */
    private function getExpiresTime($time): int
    {
        if ($time instanceof \DateTimeInterface) {
            return (int) $time->format('U');
        }

        if (\is_numeric($time)) {
            // average year in seconds
            if ($time <= 31557600) {
                $time += \time();
            }

            return (int) $time;
        }

        return (int) (new \DateTime($time))->format('U');
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->cookies);
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->cookies);
    }
}
