<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Mail;

use Hail\Exception\{
	InvalidArgumentException,
	InvalidStateException
};
use Hail\Facades\{
	Generator,
	Strings
};
use Hail\Utils\Validator;


/**
 * MIME message part.
 *
 * @property   mixed $body
 */
class MimePart
{
	/** encoding */
	const ENCODING_BASE64 = 'base64',
		ENCODING_7BIT = '7bit',
		ENCODING_8BIT = '8bit',
		ENCODING_QUOTED_PRINTABLE = 'quoted-printable';

	/** @internal */
	const EOL = "\r\n";
	const LINE_LENGTH = 76;

	/** @var array */
	private $headers = [];

	/** @var array */
	private $parts = [];

	/** @var string */
	private $body;


	/**
	 * Sets a header.
	 *
	 * @param               string
	 * @param  string|array value or pair email => name
	 * @param               bool
	 *
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function setHeader($name, $value, $append = false)
	{
		if (!$name || preg_match('#[^a-z0-9-]#i', $name)) {
			throw new InvalidArgumentException("Header name must be non-empty alphanumeric string, '$name' given.");
		}

		if ($value == null) { // intentionally ==
			if (!$append) {
				unset($this->headers[$name]);
			}

		} elseif (is_array($value)) { // email
			$tmp = &$this->headers[$name];
			if (!$append || !is_array($tmp)) {
				$tmp = [];
			}

			foreach ($value as $email => $recipient) {
				if ($recipient !== null && !Strings::checkEncoding($recipient)) {
					Validator::assert($recipient, 'unicode', "header '$name'");
				}
				if (preg_match('#[\r\n]#', $recipient)) {
					throw new InvalidArgumentException('Name must not contain line separator.');
				}
				Validator::assert($email, 'email', "header '$name'");
				$tmp[$email] = $recipient;
			}

		} else {
			$value = (string) $value;
			if (!Strings::checkEncoding($value)) {
				throw new InvalidArgumentException('Header is not valid UTF-8 string.');
			}
			$this->headers[$name] = preg_replace('#[\r\n]+#', ' ', $value);
		}

		return $this;
	}


	/**
	 * Returns a header.
	 *
	 * @param  string
	 *
	 * @return mixed
	 */
	public function getHeader($name)
	{
		return isset($this->headers[$name]) ? $this->headers[$name] : null;
	}


	/**
	 * Removes a header.
	 *
	 * @param  string
	 *
	 * @return self
	 */
	public function clearHeader($name)
	{
		unset($this->headers[$name]);

		return $this;
	}


	/**
	 * Returns an encoded header.
	 *
	 * @param  string
	 * @param  string
	 *
	 * @return string
	 */
	public function getEncodedHeader($name)
	{
		$offset = strlen($name) + 2; // colon + space

		if (!isset($this->headers[$name])) {
			return null;

		} elseif (is_array($this->headers[$name])) {
			$s = '';
			foreach ($this->headers[$name] as $email => $name) {
				if ($name != null) { // intentionally ==
					$s .= self::encodeHeader($name, $offset, true);
					$email = " <$email>";
				}
				$s .= self::append($email . ',', $offset);
			}

			return ltrim(substr($s, 0, -1)); // last comma

		} elseif (preg_match('#^(\S+; (?:file)?name=)"(.*)"\z#', $this->headers[$name], $m)) { // Content-Disposition
			$offset += strlen($m[1]);

			return $m[1] . '"' . self::encodeHeader($m[2], $offset) . '"';

		} else {
			return ltrim(self::encodeHeader($this->headers[$name], $offset));
		}
	}


	/**
	 * Returns all headers.
	 *
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->headers;
	}


	/**
	 * Sets Content-Type header.
	 *
	 * @param  string
	 * @param  string
	 *
	 * @return self
	 */
	public function setContentType($contentType, $charset = null)
	{
		$this->setHeader('Content-Type', $contentType . ($charset ? "; charset=$charset" : ''));

		return $this;
	}


	/**
	 * Sets Content-Transfer-Encoding header.
	 *
	 * @param  string
	 *
	 * @return self
	 */
	public function setEncoding($encoding)
	{
		$this->setHeader('Content-Transfer-Encoding', $encoding);

		return $this;
	}


	/**
	 * Returns Content-Transfer-Encoding header.
	 *
	 * @return string
	 */
	public function getEncoding()
	{
		return $this->getHeader('Content-Transfer-Encoding');
	}


	/**
	 * Adds or creates new multipart.
	 *
	 * @return MimePart
	 */
	public function addPart(MimePart $part = null)
	{
		return $this->parts[] = $part === null ? new self : $part;
	}


	/**
	 * Sets textual body.
	 *
	 * @return self
	 */
	public function setBody($body)
	{
		$this->body = (string) $body;

		return $this;
	}


	/**
	 * Gets textual body.
	 *
	 * @return mixed
	 */
	public function getBody()
	{
		return $this->body;
	}


	/********************* building ****************d*g**/


	/**
	 * Returns encoded message.
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 * @throws InvalidStateException
	 */
	public function getEncodedMessage()
	{
		$output = '';
		$boundary = '--------' . Generator::random();

		foreach ($this->headers as $name => $value) {
			$output .= $name . ': ' . $this->getEncodedHeader($name);
			if ($this->parts && $name === 'Content-Type') {
				$output .= ';' . self::EOL . "\tboundary=\"$boundary\"";
			}
			$output .= self::EOL;
		}
		$output .= self::EOL;

		$body = (string) $this->body;
		if ($body !== '') {
			switch ($this->getEncoding()) {
				case self::ENCODING_QUOTED_PRINTABLE:
					$output .= quoted_printable_encode($body);
					break;

				case self::ENCODING_BASE64:
					$output .= rtrim(chunk_split(base64_encode($body), self::LINE_LENGTH, self::EOL));
					break;

				case self::ENCODING_7BIT:
					$body = preg_replace('#[\x80-\xFF]+#', '', $body);
				// break intentionally omitted

				case self::ENCODING_8BIT:
					$output .= str_replace(["\x00", "\r", "\n"], ['', '', self::EOL], $body);
					break;

				default:
					throw new InvalidStateException('Unknown encoding.');
			}
		}

		if ($this->parts) {
			if (substr($output, -strlen(self::EOL)) !== self::EOL) {
				$output .= self::EOL;
			}
			foreach ($this->parts as $part) {
				$output .= '--' . $boundary . self::EOL . $part->getEncodedMessage() . self::EOL;
			}
			$output .= '--' . $boundary . '--';
		}

		return $output;
	}


	/********************* QuotedPrintable helpers ****************d*g**/


	/**
	 * Converts a 8 bit header to a string.
	 *
	 * @param  string
	 * @param  int
	 * @param  bool
	 *
	 * @return string
	 */
	private static function encodeHeader($s, & $offset = 0, $quotes = false)
	{
		if (strspn($s, "!\"#$%&\'()*+,-./0123456789:;<>@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^`abcdefghijklmnopqrstuvwxyz{|}~=? _\r\n\t") === strlen($s)) {
			if ($quotes && preg_match('#[^ a-zA-Z0-9!\#$%&\'*+/?^_`{|}~-]#', $s)) { // RFC 2822 atext except =
				return self::append('"' . addcslashes($s, '"\\') . '"', $offset);
			}

			return self::append($s, $offset);
		}

		$o = '';
		if ($offset >= 55) { // maximum for iconv_mime_encode
			$o = self::EOL . "\t";
			$offset = 1;
		}

		$filed = str_repeat(' ', $old = $offset);
		if (function_exists('iconv_mime_encode')) {
			$s = iconv_mime_encode($filed, $s, [
				'scheme' => 'B', // Q is broken
				'input-charset' => 'UTF-8',
				'output-charset' => 'UTF-8',
			]);
		} else {
			$s = $filed . ': ' . mb_encode_mimeheader($s, 'UTF-8', 'B');
		}

		$offset = strlen($s) - strrpos($s, "\n");
		$s = str_replace("\n ", "\n\t", substr($s, $old + 2)); // adds ': '
		return $o . $s;
	}


	private static function append($s, &$offset)
	{
		if ($offset + strlen($s) > self::LINE_LENGTH) {
			$offset = 1;
			$s = self::EOL . "\t" . $s;
		}
		$offset += strlen($s);

		return $s;
	}
}
