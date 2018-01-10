<?php

namespace Hail\Http\Client\Curl;

use Psr\Http\Message\ResponseInterface;

/**
 * Extended response builder.
 */
class ResponseBuilder
{
    /**
     * The response to be built.
     *
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Create builder for the given response.
     *
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Return response.
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Add headers represented by an array of header lines.
     *
     * @param string[] $headers Response headers as array of header lines.
     *
     * @return $this
     *
     * @throws \UnexpectedValueException For invalid header values.
     * @throws \InvalidArgumentException For invalid status code arguments.
     */
    public function setHeadersFromArray(array $headers)
    {
        $status = \array_shift($headers);
        $this->setStatus($status);
        foreach ($headers as $headerLine) {
            $headerLine = \trim($headerLine);
            if ('' === $headerLine) {
                continue;
            }
            $this->addHeader($headerLine);
        }

        return $this;
    }

    /**
     * Add headers represented by a single string.
     *
     * @param string $headers Response headers as single string.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException if $headers is not a string on object with __toString()
     * @throws \UnexpectedValueException For invalid header values.
     */
    public function setHeadersFromString($headers)
    {
        if (!(
            \is_string($headers) ||
            (
                \is_object($headers) &&
                \method_exists($headers, '__toString')
            )
        )) {
            throw new \InvalidArgumentException(
                __METHOD__ . '%s expects parameter 1 to be a string, ' .
                (\is_object($headers) ? \get_class($headers) : \gettype($headers)) . ' given'
            );
        }
        $this->setHeadersFromArray(\explode("\r\n", $headers));

        return $this;
    }

    /**
     * Set response status from a status string.
     *
     * @param string $statusLine Response status as a string.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException For invalid status line.
     */
    public function setStatus($statusLine)
    {
        $parts = \explode(' ', $statusLine, 3);
        if (\count($parts) < 2 || 0 !== \stripos($parts[0], 'http/')) {
            throw new \InvalidArgumentException('"' . $statusLine . '" is not a valid HTTP status line');
        }
        $reasonPhrase = \count($parts) > 2 ? $parts[2] : '';
        $this->response = $this->response
            ->withStatus((int) $parts[1], $reasonPhrase)
            ->withProtocolVersion(\substr($parts[0], 5));

        return $this;
    }

    /**
     * Add header represented by a string.
     *
     * @param string $headerLine Response header as a string.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException For invalid header names or values.
     */
    public function addHeader($headerLine)
    {
        $parts = \explode(':', $headerLine, 2);
        if (!isset($parts[1])) {
            throw new \InvalidArgumentException('"' . $headerLine . '" is not a valid HTTP header line');
        }
        $name = \trim($parts[0]);
        $value = \trim($parts[1]);
        if ($this->response->hasHeader($name)) {
            $this->response = $this->response->withAddedHeader($name, $value);
        } else {
            $this->response = $this->response->withHeader($name, $value);
        }

        return $this;
    }

    /**
     * Replace response with a new instance.
     *
     * @param ResponseInterface $response
     */
    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;
    }
}
