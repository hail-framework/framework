<?php

namespace Hail\Http\Message;


/**
 * Caching version of php://input
 */
class PhpInputStream extends Stream
{
	/**
	 * @var string
	 */
	private $cache = '';
	/**
	 * @var bool
	 */
	private $reachedEof = false;

	public function __construct()
	{
		parent::__construct(
			\fopen('php://input', 'rb')
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function __toString(): string
	{
		if ($this->reachedEof) {
			return $this->cache;
		}

		return $this->getContents();
	}


	/**
	 * {@inheritdoc}
	 */
	public function read($length): string
	{
		$content = parent::read($length);

		if ($content && !$this->reachedEof) {
			$this->cache .= $content;
		}

		if ($this->eof()) {
			$this->reachedEof = true;
		}

		return $content;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getContents($maxLength = -1): string
	{
		if ($this->reachedEof) {
			return $this->cache;
		}

		$contents = \stream_get_contents($this->stream, $maxLength);
		$this->cache .= $contents;

		if ($maxLength === -1 || $this->eof()) {
			$this->reachedEof = true;
		}

		return $contents;
	}
}