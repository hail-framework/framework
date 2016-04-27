<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Hail\Utils;


/**
 * Secure random string generator.
 */
class Generator
{

	/**
	 * Generate random string.
	 *
	 * @param  int
	 * @param  string
	 * @return string
	 */
	public static function random($length = 10, $charlist = '0-9a-zA-Z')
	{
		if ($length === 0) {
			return ''; // mcrypt_create_iv does not support zero length
		}

		$charlist = str_shuffle(preg_replace_callback('#.-.#', function ($m) {
			return implode('', range($m[0][0], $m[0][2]));
		}, $charlist));
		$chLen = strlen($charlist);

		$rand3 = random_bytes($length);

		$s = '';
		for ($i = 0; $i < $length; $i++) {
			if ($i % 5 === 0) {
				list($rand, $rand2) = explode(' ', microtime());
				$rand += lcg_value();
			}
			$rand *= $chLen;
			$s .= $charlist[($rand + $rand2 + ord($rand3[$i % strlen($rand3)])) % $chLen];
			$rand -= (int) $rand;
		}
		return $s;
	}

	public static function unique()
	{
		return uniqid(
			self::random(),
			true
		);
	}

	public static function guid()
	{
		if (function_exists('com_create_guid')) {
			return trim(com_create_guid(), '{}');
		}

		$data = random_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	public static function uuid3($namespace, $name)
	{
		if (!self::isUUID($namespace)) {
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

	public static function uuid4()
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

	public static function uuid5($namespace, $name)
	{
		if (!self::isUUID($namespace)) {
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

	public static function isUUID($uuid) {
		return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?'.
			'[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
	}
}

if (!function_exists('random_bytes')) {
	function random_bytes($length) {
		if (function_exists('openssl_random_pseudo_bytes')) {
			return openssl_random_pseudo_bytes($length);
		} else if (function_exists('mcrypt_create_iv')) { // PHP bug #52523
			return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
		} else if (@is_readable('/dev/urandom')) {
			return file_get_contents('/dev/urandom', false, null, -1, $length);
		} else {
			static $cache;
			return $cache ?: $cache = md5(serialize($_SERVER), true);
		}
	}
}

if (!function_exists('random_int')) {
	function random_int($min, $max) {
		return mt_rand($min, $max);
	}
}