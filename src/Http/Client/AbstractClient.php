<?php

declare(strict_types=1);

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractClient
{
    protected static $default = [
        'allow_redirects' => false,
        'max_redirects' => 5,
        'timeout' => 30,
        'verify' => true,
        'proxy' => null,
    ];

    protected static $types = [
        'allow_redirects' => 'boolean',
        'verify' => 'boolean',
        'max_redirects' => 'integer',
        'timeout' => ['integer', 'float'],
        'proxy' => ['NULL', 'string'],
    ];

    /**
     * @var array
     */
    private $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $this->doValidateOptions(\array_merge($options, static::$default));
    }

    /**
     * Validate a set of options and return a new array.
     *
     * @param array $options
     *
     * @return array
     */
    protected function validateOptions(array $options = []): array
    {
        if ($options === []) {
            return $this->options;
        }

        return $this->doValidateOptions($options);
    }

    /**
     * Validate a set of options and return a array.
     *
     * @param array $options
     *
     * @return array
     */
    private function doValidateOptions(array $options = []): array
    {
        if (
            isset($this->options['curl'], $options['curl']) &&
            \is_array($this->options['curl']) &&
            \is_array($options['curl'])
        ) {
            $parameters['curl'] = \array_replace($this->options['curl'], $options['curl']);
        }

        $options = \array_replace($this->options, $options);

        foreach (static::$types as $k => $v) {
            $type = \gettype($options[$k]);
            $v = (array) $v;

            $found = false;
            foreach ($v as $t) {
                if ($t === $type || ($t === 'callable' && \is_callable($options[$k]))) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $should = \implode(', ', $v);
                throw new InvalidArgumentException("'$k' options should be '$should', but get '$type'");
            }
        }

        return $options;
    }

    protected function toHeaders(array $headers): array
    {
        $return = [];

        foreach ($headers as $key => $values) {
            if (!\is_array($values)) {
                $return[] = "{$key}:{$values}";
            } else {
                foreach ($values as $value) {
                    $return[] = "{$key}:{$value}";
                }
            }
        }

        return $return;
    }

    protected function setStatus(ResponseInterface $response, string $input): ResponseInterface
    {
        $parts = explode(' ', $input, 3);
        if (\count($parts) < 2 || 0 !== stripos($parts[0], 'http/')) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid HTTP status line', $input));
        }

        $response = $response->withStatus((int)$parts[1], $parts[2] ?? '');

        return $response->withProtocolVersion((string)substr($parts[0], 5));
    }

    protected function addHeader(ResponseInterface $response, string $input): ResponseInterface
    {
        [$key, $value] = \explode(':', $input, 2);

        return $response->withAddedHeader(\trim($key), \trim($value));
    }

    private function filterHeaders(array $headers): array
    {
        $filtered = [];
        foreach ($headers as $header) {
            if (0 === \stripos($header, 'http/')) {
                $filtered = [];
                $filtered[] = \trim($header);
                continue;
            }

            // Make sure they are not empty
            $trimmed = \trim($header);
            if (false === \strpos($trimmed, ':')) {
                continue;
            }

            $filtered[] = $trimmed;
        }

        return $filtered;
    }

    protected function parseHttpHeaders(ResponseInterface $response, array $headers): ResponseInterface
    {
        $headers = $this->filterHeaders($headers);
        $statusLine = \array_shift($headers);

        try {
            $response = $this->setStatus($response, $statusLine);
        } catch (InvalidArgumentException $e) {
            \array_unshift($headers, $statusLine);
        }

        foreach ($headers as $header) {
            $response = $this->addHeader($response, $header);
        }

        return $response;
    }

}
