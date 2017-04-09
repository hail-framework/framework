<?php

namespace Hail\Http\Middleware;

use Psr\Http\Message\StreamInterface;
use DomainException;

class JsonPayload extends UrlEncodePayload
{
	/**
	 * @var string
	 */
	protected $contentType = 'application/json';

	/**
	 * @var bool
	 */
	private $associative = true;

	/**
	 * @var int
	 */
	private $depth = 512;

	/**
	 * @var int
	 */
	private $options = JSON_BIGINT_AS_STRING;

	/**
	 * Configure the returned object to be converted into a sequential array of all CSV lines
	 * or a SplTempFileObject
	 *
	 * @param bool $associative
	 *
	 * @return self
	 */
	public function associative(bool $associative = true)
	{
		$this->associative = $associative;

		return $this;
	}

	/**
	 * Configure the recursion depth.
	 *
	 * @see http://php.net/manual/en/function.json-decode.php
	 *
	 * @param int $depth
	 *
	 * @return self
	 */
	public function depth(int $depth)
	{
		$this->depth = $depth;

		return $this;
	}

	/**
	 * Configure the decode options.
	 *
	 * @see http://php.net/manual/en/function.json-decode.php
	 *
	 * @param int $options
	 *
	 * @return self
	 */
	public function options(int $options)
	{
		$this->options = $options;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function parse(StreamInterface $stream)
	{
		$json = trim((string) $stream);
		if ($json === '') {
			return [];
		}
		$data = json_decode($json, $this->associative, $this->depth, $this->options);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new DomainException(json_last_error_msg());
		}

		return $data ?: [];
	}
}