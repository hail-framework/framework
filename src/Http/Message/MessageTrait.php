<?php

declare(strict_types=1);

namespace Hail\Http\Message;

use Hail\Http\Helpers;
use Hail\Http\Factory;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Trait implementing functionality common to requests and responses.
 *
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Feng Hao <flyinghail@msn.com>
 */
trait MessageTrait
{
    /** @var array Map of all registered headers, as original name => array of values */
    private $headers = [];

    /** @var array Map of lowercase header name => original name at registration */
    private $headerNames = [];

    /** @var string */
    private $protocol = '1.1';

    /** @var StreamInterface */
    private $stream;

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion($version): MessageInterface
    {
        if ($this->protocol === $version) {
            return $this;
        }

        $this->validateProtocolVersion($version);

        $new = clone $this;
        $new->protocol = $version;

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($header): bool
    {
        return isset($this->headers[$header]) || isset($this->headerNames[\strtolower($header)]);
    }

    public function getHeader($header): array
    {
        if (isset($this->headers[$header])) {
            return $this->headers[$header];
        }

        $header = \strtolower($header);

        if (!isset($this->headerNames[$header])) {
            return [];
        }

        $header = $this->headerNames[$header];

        return $this->headers[$header];
    }

    public function getHeaderLine($header): string
    {
        return \implode(', ', $this->getHeader($header));
    }

    public function withHeader($header, $value): self
    {
        if (!\is_array($value)) {
            $value = [$value];
        }

        $header = Helpers::normalizeHeaderName($header);
        $value = Helpers::trimHeaderValues($value);
        $normalized = \strtolower($header);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }
        $new->headerNames[$normalized] = $header;
        $new->headers[$header] = $value;

        return $new;
    }

    public function withAddedHeader($header, $value): self
    {
        if (!\is_array($value)) {
            $value = [$value];
        }

        $header = Helpers::normalizeHeaderName($header);
        $value = Helpers::trimHeaderValues($value);
        $normalized = \strtolower($header);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            $header = $this->headerNames[$normalized];
            $new->headers[$header] = array_merge($this->headers[$header], $value);
        } else {
            $new->headerNames[$normalized] = $header;
            $new->headers[$header] = $value;
        }

        return $new;
    }

    public function withoutHeader($header): MessageInterface
    {
        $normalized = \strtolower($header);

        if (!isset($this->headerNames[$normalized])) {
            return $this;
        }

        $header = $this->headerNames[$normalized];

        $new = clone $this;
        unset($new->headers[$header], $new->headerNames[$normalized]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        if (!$this->stream) {
            $this->stream = Factory::stream('');
        }

        return $this->stream;
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($body === $this->stream) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;

        return $new;
    }

    private function setHeaders(array $headers)
    {
        $this->headerNames = $this->headers = [];

        foreach ($headers as $header => $value) {
            if (!\is_array($value)) {
                $value = [$value];
            }

            $header = Helpers::normalizeHeaderName($header);
            $value = Helpers::trimHeaderValues($value);

            $normalized = \strtolower($header);
            if (isset($this->headerNames[$normalized])) {
                $header = $this->headerNames[$normalized];
                $this->headers[$header] = \array_merge($this->headers[$header], $value);
            } else {
                $this->headerNames[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
    }

    /**
     * Validate the HTTP protocol version
     *
     * @param string $version
     *
     * @throws \InvalidArgumentException on invalid HTTP protocol version
     */
    private function validateProtocolVersion($version): void
    {
        if (empty($version)) {
            throw new \InvalidArgumentException(
                'HTTP protocol version can not be empty'
            );
        }
        if (!\is_string($version)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported HTTP protocol version; must be a string, received %s',
                (\is_object($version) ? \get_class($version) : \gettype($version))
            ));
        }

        // HTTP/1 uses a "<major>.<minor>" numbering scheme to indicate
        // versions of the protocol, while HTTP/2 does not.
        if (!\preg_match('#^(1\.[01]|2)$#', $version)) {
            throw new \InvalidArgumentException(\sprintf(
                'Unsupported HTTP protocol version "%s" provided',
                $version
            ));
        }
    }
}
