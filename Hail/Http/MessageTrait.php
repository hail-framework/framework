<?php

declare(strict_types=1);

namespace Hail\Http;

use Psr\Http\Message\StreamInterface;

/**
 * Trait implementing functionality common to requests and responses.
 *
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
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

	public function getProtocolVersion()
	{
		return $this->protocol;
	}

	public function withProtocolVersion($version)
	{
		if ($this->protocol === $version) {
			return $this;
		}

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
		return isset($this->headers[$header]) || isset($this->headerNames[strtolower($header)]);
	}

	public function getHeader($header): array
	{
		if (isset($this->headers[$header])) {
			return $this->headers[$header];
		}

		$header = strtolower($header);

		if (!isset($this->headerNames[$header])) {
			return [];
		}

		$header = $this->headerNames[$header];

		return $this->headers[$header];
	}

	public function getHeaderLine($header): string
	{
		return implode(', ', $this->getHeader($header));
	}

	public function withHeader($header, $value): self
	{
		if (!is_array($value)) {
			$value = [$value];
		}

		$value = Helpers::trimHeaderValues($value);
		$normalized = strtolower($header);

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
		if (!is_array($value)) {
			$value = [$value];
		}

		$value = Helpers::trimHeaderValues($value);
		$normalized = strtolower($header);

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

	public function withoutHeader($header): self
	{
		$normalized = strtolower($header);

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

	public function withBody(StreamInterface $body): self
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
			if (!is_array($value)) {
				$value = [$value];
			}

			$value = Helpers::trimHeaderValues($value);
			$normalized = strtolower($header);
			if (isset($this->headerNames[$normalized])) {
				$header = $this->headerNames[$normalized];
				$this->headers[$header] = array_merge($this->headers[$header], $value);
			} else {
				$this->headerNames[$normalized] = $header;
				$this->headers[$header] = $value;
			}
		}
	}
}
