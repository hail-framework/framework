<?php
/*
 * This class some code from the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */

namespace Hail\Http;

/**
 * Class Header
 *
 * @package Hail\Http
 */
class Header implements \IteratorAggregate, \Countable
{
    public const DISPOSITION_ATTACHMENT = 'attachment';
    public const DISPOSITION_INLINE = 'inline';

    /**
     * @var array
     */
    protected $computedCacheControl = [];

    /**
     * @var array
     */
    protected $headerNames = [];

    protected $headers = [];
    protected $cacheControl = [];

    public function __construct()
    {
        $this->set('Cache-Control', '');
    }

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     */
    public function keys()
    {
        return \array_keys($this->all());
    }

    /**
     * Replaces the current HTTP headers by a new set.
     *
     * @param array $headers An array of HTTP headers
     *
     * @throws \LogicException
     */
    public function replace(array $headers = []): void
    {
        $this->headerNames = [];
        $this->headers = [];

        $headers !== [] && $this->add($headers);

        if (!isset($this->headers['Cache-Control'])) {
            $this->set('Cache-Control', '');
        }
    }

    /**
     * Adds new headers the current HTTP headers set.
     *
     * @param array $headers An array of HTTP headers
     *
     * @throws \LogicException
     */
    public function add(array $headers): void
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values, false);
        }
    }

    /**
     * Returns a header value by name.
     *
     * @param string $key     The header name
     * @param mixed  $default The default value
     *
     * @return array array of values otherwise
     */
    public function get($key, $default = null): array
    {
        if (!$this->has($key)) {
            if (\is_array($default)) {
                return $default;
            }

            return $default === null ? [] : [$default];
        }

        return $this->headers[Helpers::normalizeHeaderName($key)];
    }

    /**
     * Returns the headers.
     *
     * @return array An array of headers
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * Sets a header by name.
     *
     * @param string       $key     The key
     * @param string|array $values  The value or an array of values
     * @param bool         $replace Whether to replace the actual value or not (true by default)
     */
    public function set($key, $values, $replace = true)
    {
        if (\strpos($key, '_')) {
            $key = \str_replace('_', '-', $key);
        }

        $uniqueKey = \strtolower($key);
        if ($uniqueKey === 'set-cookie') {
            throw new \LogicException('Please use Hail\\Http\\Cookie to set cookie value');
        }

        $key = Helpers::normalizeHeaderName($uniqueKey);

        $this->headerNames[$uniqueKey] = $key;

        $values = \array_values((array) $values);

        if (true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $values;
        } else {
            $this->headers[$key] = \array_merge($this->headers[$key], $values);
        }

        if ('cache-control' === $uniqueKey) {
            $this->cacheControl = $this->parseCacheControl($values[0]);
        }

        // ensure the cache-control header has sensible defaults
        if (\in_array($uniqueKey, ['cache-control', 'etag', 'last-modified', 'expires'], true)) {
            $computed = $this->computeCacheControlValue();
            $this->headers['Cache-Control'] = [$computed];
            $this->headerNames['cache-control'] = 'Cache-Control';
            $this->computedCacheControl = $this->parseCacheControl($computed);
        }
    }

    /**
     * Returns true if the HTTP header is defined.
     *
     * @param string $key The HTTP header
     *
     * @return bool true if the parameter exists, false otherwise
     */
    public function has($key)
    {
        if (\strpos($key, '_')) {
            $key = \str_replace('_', '-', $key);
        }

        return isset($this->headerNames[\strtolower($key)]);
    }

    /**
     * Returns true if the given HTTP header contains the given value.
     *
     * @param string $key   The HTTP header name
     * @param string $value The HTTP value
     *
     * @return bool true if the value is contained in the header, false otherwise
     */
    public function contains($key, $value)
    {
        return \in_array($value, $this->get($key), true);
    }

    /**
     * Removes a header.
     *
     * @param string $key The HTTP header name
     */
    public function remove($key)
    {
        if (\strpos($key, '_')) {
            $key = \str_replace('_', '-', $key);
        }

        $uniqueKey = \strtolower($key);
        if ($uniqueKey === 'set-cookie') {
            throw new \LogicException('Please use Hail\\Http\\Cookie to remove cookie value');
        }

        unset($this->headerNames[$uniqueKey]);

        $key = Helpers::normalizeHeaderName($uniqueKey);
        unset($this->headers[$key]);

        if ('cache-control' === $uniqueKey) {
            $this->cacheControl = [];
            $this->computedCacheControl = [];
        }
    }

    /**
     * Returns true if the Cache-Control directive is defined.
     *
     * @param string $key The Cache-Control directive
     *
     * @return bool true if the directive exists, false otherwise
     */
    public function hasCacheControlDirective($key)
    {
        return isset($this->computedCacheControl[$key]) || \array_key_exists($key, $this->computedCacheControl);
    }

    /**
     * Returns a Cache-Control directive value by name.
     *
     * @param string $key The directive name
     *
     * @return mixed|null The directive value if defined, null otherwise
     */
    public function getCacheControlDirective($key)
    {
        return $this->computedCacheControl[$key] ?? null;
    }

    /**
     * Generates a HTTP Content-Disposition field-value.
     *
     * @param string $disposition      One of "inline" or "attachment"
     * @param string $filename         A unicode string
     * @param string $filenameFallback A string containing only ASCII characters that
     *                                 is semantically equivalent to $filename. If the filename is already ASCII,
     *                                 it can be omitted, or just copied from $filename
     *
     * @return string A string suitable for use as a Content-Disposition field-value
     *
     * @throws \InvalidArgumentException
     *
     * @see RFC 6266
     */
    public function makeDisposition($disposition, $filename, $filenameFallback = '')
    {
        if (!\in_array($disposition, [self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE], true)) {
            throw new \InvalidArgumentException(\sprintf('The disposition must be either "%s" or "%s".',
                self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE));
        }

        if (!$filenameFallback) {
            $filenameFallback = $filename;
        }

        // filenameFallback is not ASCII.
        if (!\preg_match('/^[\x20-\x7e]*$/', $filenameFallback)) {
            throw new \InvalidArgumentException('The filename fallback must only contain ASCII characters.');
        }

        // percent characters aren't safe in fallback.
        if (false !== \strpos($filenameFallback, '%')) {
            throw new \InvalidArgumentException('The filename fallback cannot contain the "%" character.');
        }

        // path separators aren't allowed in either.
        if (false !== \strpos($filename, '/') || false !== \strpos($filename, '\\') || false !== \strpos($filenameFallback,
                '/') || false !== \strpos($filenameFallback, '\\')
        ) {
            throw new \InvalidArgumentException('The filename and the fallback cannot contain the "/" and "\\" characters.');
        }

        $output = $disposition . '; filename="' . \str_replace('"', '\\"', $filenameFallback) . '"';

        if ($filename !== $filenameFallback) {
            $output .= '; filename*=utf-8\'\'' . \rawurlencode($filename);
        }

        return $output;
    }

    /**
     * Returns the calculated value of the cache-control header.
     *
     * This considers several other headers and calculates or modifies the
     * cache-control header to a sensible, conservative value.
     *
     * @return string
     */
    protected function computeCacheControlValue()
    {
        if (!$this->cacheControl && !$this->has('ETag') && !$this->has('Last-Modified') && !$this->has('Expires')) {
            return 'no-cache, private';
        }

        if (!$this->cacheControl) {
            // conservative by default
            return 'private, must-revalidate';
        }

        $header = $this->getCacheControlHeader();
        if (isset($this->cacheControl['public']) || isset($this->cacheControl['private'])) {
            return $header;
        }

        // public if s-maxage is defined, private otherwise
        if (!isset($this->cacheControl['s-maxage'])) {
            return $header . ', private';
        }

        return $header;
    }

    /**
     * Returns the HTTP header value converted to a date.
     *
     * @param string    $key     The parameter key
     * @param \DateTime $default The default value
     *
     * @return null|\DateTime The parsed DateTime or the default value if the header does not exist
     *
     * @throws \RuntimeException When the HTTP header is not parseable
     */
    public function getDate($key, \DateTime $default = null)
    {
        $value = $this->get($key);
        if ([] === $value) {
            return $default;
        }

        $value = $value[0];
        if (false === $date = \DateTime::createFromFormat(DATE_RFC2822, $value)) {
            throw new \RuntimeException(\sprintf('The %s HTTP header is not parseable (%s).', $key, $value));
        }

        return $date;
    }

    /**
     * Adds a custom Cache-Control directive.
     *
     * @param string $key   The Cache-Control directive name
     * @param mixed  $value The Cache-Control directive value
     */
    public function addCacheControlDirective($key, $value = true)
    {
        $this->cacheControl[$key] = $value;

        $this->set('Cache-Control', $this->getCacheControlHeader());
    }

    /**
     * Removes a Cache-Control directive.
     *
     * @param string $key The Cache-Control directive
     */
    public function removeCacheControlDirective($key)
    {
        unset($this->cacheControl[$key]);

        $this->set('Cache-Control', $this->getCacheControlHeader());
    }

    /**
     * Returns an iterator for headers.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->headers);
    }

    /**
     * Returns the number of headers.
     *
     * @return int The number of headers
     */
    public function count()
    {
        return \count($this->headers);
    }

    protected function getCacheControlHeader()
    {
        $parts = [];
        \ksort($this->cacheControl);
        foreach ($this->cacheControl as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (\preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"' . $value . '"';
                }

                $parts[] = "$key=$value";
            }
        }

        return \implode(', ', $parts);
    }

    /**
     * Parses a Cache-Control HTTP header.
     *
     * @param string $header The value of the Cache-Control HTTP header
     *
     * @return array An array representing the attribute values
     */
    protected function parseCacheControl($header)
    {
        $cacheControl = [];
        \preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cacheControl[\strtolower($match[1])] = $match[3] ?? ($match[2] ?? true);
        }

        return $cacheControl;
    }
}