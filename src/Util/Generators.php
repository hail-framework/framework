<?php
namespace Hail\Util;

use InvalidArgumentException;

/**
 * Secure random string generator.
 * This class provides the static methods `uuid3()`, `uuid4()`, and
 * `uuid5()` for generating version 3, 4, and 5 UUIDs as specified in RFC 4122.
 *
 * @author Feng Hao <flyinghail@msn.com>
 */
class Generators
{
	/**
	 * When this namespace is specified, the name string is a fully-qualified domain name.
	 *
	 * @link http://tools.ietf.org/html/rfc4122#appendix-C
	 */
	public const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
	/**
	 * When this namespace is specified, the name string is a URL.
	 *
	 * @link http://tools.ietf.org/html/rfc4122#appendix-C
	 */
    public const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
	/**
	 * When this namespace is specified, the name string is an ISO OID.
	 *
	 * @link http://tools.ietf.org/html/rfc4122#appendix-C
	 */
    public const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';
	/**
	 * When this namespace is specified, the name string is an X.500 DN in DER or a text output format.
	 *
	 * @link http://tools.ietf.org/html/rfc4122#appendix-C
	 */
    public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';
	/**
	 * The nil UUID is special form of UUID that is specified to have all 128 bits set to zero.
	 *
	 * @link http://tools.ietf.org/html/rfc4122#section-4.1.7
	 */
    public const NIL = '00000000-0000-0000-0000-000000000000';

	/**
	 * Generate random string.
	 *
	 * @param  int
	 * @param  string
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public static function random(int $length = 10, string $charList = '0-9a-zA-Z'): string
	{
		$charList = \count_chars(\preg_replace_callback('#.-.#', function (array $m) {
			return \implode('', \range($m[0][0], $m[0][2]));
		}, $charList), 3);
		$chLen = \strlen($charList);

		if ($length < 1) {
			throw new InvalidArgumentException('Length must be greater than zero.');
		}

		if ($chLen < 2) {
			throw new InvalidArgumentException('Character list must contain as least two chars.');
		}

		$res = '';
		for ($i = 0; $i < $length; $i++) {
			$res .= $charList[\random_int(0, $chLen - 1)];
		}

		return $res;
	}

	/**
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public static function unique(): string
	{
		return \uniqid(
			static::random(),
			true
		);
	}

	/**
	 * @return string
	 */
	public static function guid(): string
	{
		if (\function_exists('\com_create_guid')) {
			return \trim(\com_create_guid(), '{}');
		}

		$data = \random_bytes(16);
		$data[6] = \chr(\ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = \chr(\ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
		return \vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split(\bin2hex($data), 4));
	}

	/**
	 * @param string $namespace
	 * @param string $name
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public static function uuid3(string $namespace, string $name): string
	{
		$bytes = static::getBytes($namespace);

		$hash = \md5($bytes . $name);

		return static::uuidFromHash($hash, 3);
	}

	/**
	 * @return string
	 */
	public static function uuid4(): string
	{
		$bytes = \random_bytes(16);
		$hash = \bin2hex($bytes);

		return static::uuidFromHash($hash, 4);
	}

	/**
	 * @param string $namespace
	 * @param string $name
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public static function uuid5(string $namespace, string $name): string
	{
		$bytes = static::getBytes($namespace);

		$hash = \sha1($bytes . $name);

		return static::uuidFromHash($hash, 5);
	}

	/**
	 * @param string $uuid
	 *
	 * @return bool
	 */
	public static function isUUID(string $uuid): bool
	{
		return \preg_match('/^(urn:)?(uuid:)?(\{)?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{12}(?(3)\}|)$/i', $uuid) === 1;
	}

	/**
	 * @param string $uuid
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	private static function getBytes(string $uuid): string
	{
		if (!static::isUUID($uuid)) {
			throw new InvalidArgumentException('Invalid UUID string: ' . $uuid);
		}

		// Get hexadecimal components of UUID
		$hex = \str_replace([
			'urn:',
			'uuid:',
			'-',
			'{',
			'}',
		], '', $uuid);

		// Binary Value
		$str = '';
		// Convert UUID to bits
		for ($i = 0, $n = \strlen($hex); $i < $n; $i += 2) {
			$str .= \chr(\hexdec($hex[$i] . $hex[$i + 1]));
		}

		return $str;
	}

	private static function uuidFromHash($hash, $version)
	{
		return \sprintf('%08s-%04s-%04x-%04x-%12s',
			// 32 bits for "time_low"
			\substr($hash, 0, 8),
			// 16 bits for "time_mid"
			\substr($hash, 8, 4),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number
			(\hexdec(\substr($hash, 12, 4)) & 0x0fff) | $version << 12,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			(\hexdec(\substr($hash, 16, 4)) & 0x3fff) | 0x8000,
			// 48 bits for "node"
			\substr($hash, 20, 12));
	}
}