<?php
/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Hail\Utils;

use Hail\Exception\InvalidArgumentException;

/**
 * Secure random string generator.
 * @author Hao Feng <flyinghail@msn.com>
 */
class Generator
{
	use Singleton;

	/**
	 * Generate random string.
	 *
	 * @param  int
	 * @param  string
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public function random(int $length = 10, string $charList = '0-9a-zA-Z') :string
	{
		$charList = count_chars(preg_replace_callback('#.-.#', function (array $m) {
			return implode('', range($m[0][0], $m[0][2]));
		}, $charList), 3);
		$chLen = strlen($charList);

		if ($length < 1) {
			throw new InvalidArgumentException('Length must be greater than zero.');
		} elseif ($chLen < 2) {
			throw new InvalidArgumentException('Character list must contain as least two chars.');
		}

		$res = '';
		for ($i = 0; $i < $length; $i++) {
			$res .= $charList[random_int(0, $chLen - 1)];
		}

		return $res;
	}

	public function unique()
	{
		return uniqid(
			$this->random(),
			true
		);
	}

	public function guid()
	{
		if (function_exists('com_create_guid')) {
			return trim(com_create_guid(), '{}');
		}

		$data = random_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	public function uuid3($namespace, $name)
	{
		if (!$this->isUUID($namespace)) {
			return false;
		}

		// Get hexadecimal components of namespace
		$nhex = str_replace(['-', '{', '}'], '', $namespace);

		// Binary Value
		$nstr = '';

		// Convert Namespace UUID to bits
		for ($i = 0, $n = strlen($nhex); $i < $n; $i += 2) {
			$nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
		}

		// Calculate hash value
		$hash = md5($nstr . $name);

		return sprintf('%08s-%04s-%04x-%04x-%12s',

			// 32 bits for "time_low"
			substr($hash, 0, 8),

			// 16 bits for "time_mid"
			substr($hash, 8, 4),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 3
			(hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			(hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,

			// 48 bits for "node"
			substr($hash, 20, 12)
		);
	}

	public function uuid4()
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

			// 32 bits for "time_low"
			random_int(0, 0xffff), random_int(0, 0xffff),

			// 16 bits for "time_mid"
			random_int(0, 0xffff),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			random_int(0, 0x0fff) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			random_int(0, 0x3fff) | 0x8000,

			// 48 bits for "node"
			random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
		);
	}

	public function uuid5($namespace, $name)
	{
		if (!$this->isUUID($namespace)) {
			return false;
		}

		// Get hexadecimal components of namespace
		$nhex = str_replace(['-', '{', '}'], '', $namespace);

		// Binary Value
		$nstr = '';

		// Convert Namespace UUID to bits
		for ($i = 0, $n = strlen($nhex); $i < $n; $i += 2) {
			$nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
		}

		// Calculate hash value
		$hash = sha1($nstr . $name);

		return sprintf('%08s-%04s-%04x-%04x-%12s',

			// 32 bits for "time_low"
			substr($hash, 0, 8),

			// 16 bits for "time_mid"
			substr($hash, 8, 4),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 5
			(hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			(hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,

			// 48 bits for "node"
			substr($hash, 20, 12)
		);
	}

	public function isUUID($uuid)
	{
		return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
	}
}