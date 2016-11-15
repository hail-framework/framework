<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/13 0013
 * Time: 23:06
 */

namespace Hail\Utils;

use Hail\Utils\Exception;

!defined('HAIL_CRYPTO_ASCII_TYPE') || define('HAIL_CRYPTO_ASCII_TYPE', 'hex');

/**
 * Class Crypto
 *
 * @package Hail\Utils
 */
class Crypto
{
	const HEADER_VERSION_SIZE = 4;
	const MINIMUM_CIPHERTEXT_SIZE = 84;

	const CURRENT_VERSION = "\xDE\xF5\x02\x00";

	const CIPHER_METHOD = 'aes-256-ctr';
	const BLOCK_BYTE_SIZE = 16;
	const KEY_BYTE_SIZE = 32;
	const SALT_BYTE_SIZE = 32;
	const MAC_BYTE_SIZE = 32;
	const HASH_TYPE = 'sha256';
	const ENCRYPTION_INFO_STRING = 'Hail|V1|KeyForEncryption';
	const AUTHENTICATION_INFO_STRING = 'Hail|V1|KeyForAuthentication';

	const PBKDF2_ITERATIONS = 100000;

	public static $hashList = [
//		'md5' => 16,
		'sha1' => 20,
		'sha224' => 28,
		'sha256' => 32,
		'sha384' => 48,
		'sha512' => 64,
//		'ripemd128' => 16,
		'ripemd160' => 20,
		'ripemd256' => 32,
		'ripemd320' => 40,
		'whirlpool' => 64,
	];

	const RETURN_RAW = 'raw';
	const RETURN_HEX = 'hex';
	const RETURN_STR = 'str';
	const RETURN_BASE64 = 'base64';

	/**
	 * @param $password
	 *
	 * @return bool|string
	 */
	public static function password($password)
	{
		return password_hash($password, PASSWORD_DEFAULT);
	}

	/**
	 * @param $password
	 * @param $hash
	 *
	 * @return bool|string
	 */
	public static function verifyPassword($password, $hash)
	{
		if (password_verify($password, $hash)) {
			if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
				return self::password($password);
			}

			return true;
		}

		return false;
	}

	/**
	 * @param $text
	 * @param string $return
	 *
	 * @return string
	 */
	public static function hash($text, $return = self::RETURN_HEX)
	{
		if (self::RETURN_HEX === $return) {
			return hash(self::HASH_TYPE, $text, false);
		}

		$raw = hash(self::HASH_TYPE, $text, true);

		return self::fromBin($raw, $return);
	}

	/**
	 * @param $text
	 * @param $hash
	 * @param string $return
	 *
	 * @return bool|string
	 */
	public static function verifyHash($hash, $text, $return = self::RETURN_HEX)
	{
		return hash_equals($hash, self::hash($text, $return));
	}

	/**
	 * @param $text
	 * @param $salt
	 * @param string $return
	 *
	 * @return string
	 */
	public static function hmac($text, $salt, $return = self::RETURN_HEX)
	{
		if (self::RETURN_HEX === $return) {
			return hash_hmac(self::HASH_TYPE, $text, $salt, false);
		}

		$raw = hash_hmac(self::HASH_TYPE, $text, $salt, true);

		return self::fromBin($raw, $return);
	}

	/**
	 * @param $text
	 * @param $salt
	 * @param $hash
	 * @param string $return
	 *
	 * @return bool|string
	 */
	public static function verifyHMAC($hash, $text, $salt, $return = self::RETURN_HEX)
	{
		return hash_equals($hash, self::hmac($text, $salt, $return));
	}

	public static function createKey($return = self::RETURN_HEX)
	{
		return self::fromBin(
			random_bytes(self::KEY_BYTE_SIZE),
			$return
		);
	}

	/**
	 * Encrypts a string with a Key.
	 *
	 * @param string $plaintext
	 * @param string $key
	 * @param string $return
	 * @param bool $password
	 *
	 * @throws Exception\Crypto
	 *
	 * @return string
	 */
	public static function encrypt($plaintext, string $key, $return = self::RETURN_HEX, $password = false)
	{
		if (mb_strlen($key, '8bit') !== self::KEY_BYTE_SIZE) {
			throw new Exception\Crypto('Bad key length.');
		}

		$salt = random_bytes(self::SALT_BYTE_SIZE);

		list($authKey, $encryptKey) = self::deriveKeys($key, $salt, $password);

		$iv = random_bytes(self::BLOCK_BYTE_SIZE);

		$cipherText = openssl_encrypt(
			$plaintext,
			self::CIPHER_METHOD,
			$encryptKey,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ($cipherText === false) {
			throw new Exception\Crypto(
				'openssl_encrypt() failed.'
			);
		}

		$cipherText = self::CURRENT_VERSION . $salt . $iv . $cipherText;
		$auth = self::hmac($cipherText, $authKey, self::RETURN_RAW);
		$cipherText .= $auth;

		return self::fromBin($cipherText, $return);
	}

	/**
	 * Encrypts a string with a password, using a slow key derivation function
	 * to make password cracking more expensive.
	 *
	 * @param string $plaintext
	 * @param string $password
	 * @param string $return
	 *
	 * @throws Exception\Crypto
	 *
	 * @return string
	 */
	public static function encryptWithPassword($plaintext, $password, $return = self::RETURN_HEX)
	{
		return self::encrypt($plaintext, $password, $return, true);
	}

	/**
	 * Decrypts a cipher text to a string with a Key.
	 *
	 * @param string $cipherText
	 * @param string $key
	 * @param string $from
	 * @param bool $password
	 *
	 * @throws Exception\Crypto
	 *
	 * @return string
	 */
	public static function decrypt($cipherText, string $key, $from = self::RETURN_HEX, $password = false)
	{
		$cipherText = self::toBin($cipherText, $from);
		if ($cipherText === false) {
			throw new Exception\Crypto('Ciphertext has invalid base64 encoding.');
		}

		if (mb_strlen($cipherText, '8bit') < self::MINIMUM_CIPHERTEXT_SIZE) {
			throw new Exception\Crypto('Ciphertext is too short.');
		}

		// Get and check the version header.
		$header = mb_substr($cipherText, 0, self::HEADER_VERSION_SIZE, '8bit');
		if ($header !== self::CURRENT_VERSION) {
			throw new Exception\Crypto('Bad version header.');
		}

		// Get the salt.
		$salt = mb_substr(
			$cipherText,
			self::HEADER_VERSION_SIZE,
			self::SALT_BYTE_SIZE,
			'8bit'
		);
		if ($salt === false) {
			throw new Exception\Crypto('Environment is broken');
		}

		// Get the IV.
		$iv = mb_substr(
			$cipherText,
			self::HEADER_VERSION_SIZE + self::SALT_BYTE_SIZE,
			self::BLOCK_BYTE_SIZE,
			'8bit'
		);
		if ($iv === false) {
			throw new Exception\Crypto('Environment is broken');
		}

		// Get the HMAC.
		$hmac = mb_substr(
			$cipherText,
			mb_strlen($cipherText, '8bit') - self::MAC_BYTE_SIZE,
			self::MAC_BYTE_SIZE,
			'8bit'
		);
		if ($hmac === false) {
			throw new Exception\Crypto('Environment is broken');
		}

		// Get the actual encrypted ciphertext.
		$encrypted = mb_substr(
			$cipherText,
			self::HEADER_VERSION_SIZE + self::SALT_BYTE_SIZE +
			self::BLOCK_BYTE_SIZE,
			mb_strlen($cipherText, '8bit') - self::MAC_BYTE_SIZE - self::SALT_BYTE_SIZE -
			self::BLOCK_BYTE_SIZE - self::HEADER_VERSION_SIZE,
			'8bit'
		);
		if ($encrypted === false) {
			throw new Exception\Crypto('Environment is broken');
		}
		// Derive the separate encryption and authentication keys from the key
		// or password, whichever it is.
		list($authKey, $encryptKey) = self::deriveKeys($key, $salt, $password);

		if (false === self::verifyHMAC($hmac, $header . $salt . $iv . $encrypted, $authKey, self::RETURN_RAW)) {
			throw new Exception\Crypto('Integrity check failed.');
		}

		$plaintext = openssl_decrypt(
			$encrypted,
			self::CIPHER_METHOD,
			$encryptKey,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ($plaintext === false) {
			throw new Exception\Crypto('openssl_decrypt() failed.');
		}

		return $plaintext;
	}

	/**
	 * Decrypts a ciphertext to a string with a password, using a slow key
	 * derivation function to make password cracking more expensive.
	 *
	 * @param string $cipherText
	 * @param string $password
	 * @param string $from
	 *
	 * @throws Exception\Crypto
	 *
	 * @return string
	 */
	public static function decryptWithPassword($cipherText, $password, $from = self::RETURN_HEX)
	{
		return self::decrypt($cipherText, $password, $from, true);
	}

	/**
	 * Derives authentication and encryption keys from the secret, using a slow
	 * key derivation function if the secret is a password.
	 *
	 * @param string $key
	 * @param $salt
	 * @param bool $password
	 *
	 * @throws Exception\Crypto
	 *
	 * @return array
	 */
	private static function deriveKeys(string $key, $salt, $password = false)
	{
		if ($password) {
			$preHash = self::hash($key, self::RETURN_RAW);
			$key = self::pbkdf2(
				self::HASH_TYPE,
				$preHash,
				$salt,
				self::PBKDF2_ITERATIONS,
				self::KEY_BYTE_SIZE,
				true
			);
		}

		$authKey = self::hkdf(
			self::HASH_TYPE,
			$key,
			self::KEY_BYTE_SIZE,
			self::AUTHENTICATION_INFO_STRING,
			$salt
		);

		$encryptKey = self::hkdf(
			self::HASH_TYPE,
			$key,
			self::KEY_BYTE_SIZE,
			self::ENCRYPTION_INFO_STRING,
			$salt
		);

		return [$authKey, $encryptKey];
	}

	/**
	 * @param $raw
	 * @param $return
	 *
	 * @return string
	 */
	public static function fromBin($raw, $return)
	{
		switch ($return) {
			case self::RETURN_HEX:
				return bin2hex($raw);

			case self::RETURN_BASE64:
				return base64_encode($raw);

			case self::RETURN_STR:
				$field = strtoupper(bin2hex($raw));
				$field = chunk_split($field, 2, '\x');

				return '\x' . substr($field, 0, -2);

			case self::RETURN_RAW:
			default:
				return $raw;
		}
	}

	/**
	 * @param $raw
	 * @param $from
	 *
	 * @return string
	 */
	public static function toBin($raw, $from)
	{
		switch ($from) {
			case self::RETURN_HEX:
				return hex2bin($raw);

			case self::RETURN_BASE64:
				return base64_decode($raw);

			case self::RETURN_STR:
				return hex2bin(str_replace('\x', '', $raw));

			case self::RETURN_RAW:
			default:
				return $raw;
		}
	}

	/**
	 * Computes the HKDF key derivation function specified in
	 * http://tools.ietf.org/html/rfc5869.
	 *
	 * @param string $hash Hash Function
	 * @param string $ikm Initial Keying Material
	 * @param int $length How many bytes?
	 * @param string $info What sort of key are we deriving?
	 * @param string $salt
	 *
	 * @throws Exception\Crypto
	 *
	 * @return string
	 */
	public static function hkdf(string $hash, string $ikm, int $length, $info = '', $salt = null)
	{
		$hashLength = self::$hashList[$hash] ?? mb_strlen(hash_hmac($hash, '', '', true), '8bit');
		if (empty($length) || !is_int($length) || $length < 0 || $length > 255 * $hashLength) {
			throw new Exception\Crypto('Bad output length requested of HKDF.');
		}

		if ($salt === null) {
			$salt = str_repeat("\x00", $hashLength);
		}

		$prk = hash_hmac($hash, $ikm, $salt, true);
		$t = '';
		$lastBlock = '';
		$blocks = ceil($length / $hashLength);
		for ($i = 1; $i <= $blocks; ++$i) {
			$lastBlock = hash_hmac(
				$hash,
				$lastBlock . $info . chr($i),
				$prk,
				true
			);
			$t .= $lastBlock;
		}

		return mb_substr($t, 0, $length, '8bit');
	}

	/**
	 * Computes the PBKDF2 password-based key derivation function.
	 *
	 * The PBKDF2 function is defined in RFC 2898. Test vectors can be found in
	 * RFC 6070. This implementation of PBKDF2 was originally created by Taylor
	 * Hornby, with improvements from http://www.variations-of-shadow.com/.
	 *
	 * @param string $algorithm The hash algorithm to use. Recommended: SHA256
	 * @param string $password The password.
	 * @param string $salt A salt that is unique to the password.
	 * @param int $count Iteration count. Higher is better, but slower. Recommended: At least 1000.
	 * @param int $length The length of the derived key in bytes.
	 * @param bool $raw If true, the key is returned in raw binary format. Hex encoded otherwise.
	 *
	 * @throws Exception\Crypto
	 *
	 * @return string A $key_length-byte key derived from the password and salt.
	 */
	public static function pbkdf2(string $algorithm, string $password, string $salt, int $count, int $length, bool $raw = false)
	{
		$algorithm = strtolower($algorithm);
		// Whitelist, or we could end up with people using CRC32.
		if (!isset(self::$hashList[$algorithm])) {
			throw new Exception\Crypto('Algorithm is not a secure cryptographic hash function.');
		}

		if ($count <= 0 || $length <= 0) {
			throw new Exception\Crypto('Invalid PBKDF2 parameters.');
		}

		// The output length is in NIBBLES (4-bits) if $raw_output is false!
		if (!$raw) {
			$length *= 2;
		}

		return hash_pbkdf2($algorithm, $password, $salt, $count, $length, $raw);
	}

	/**
	 * @param $data
	 * @param $publicKey
	 *
	 * @return bool|string
	 */
	public static function encryptRsa($data, $publicKey)
	{
		$key = openssl_pkey_get_public($publicKey);

		$encrypted = '';
		if (!openssl_public_encrypt($data, $encrypted, $key)) {
			return false;
		}
		openssl_pkey_free($key);

		return base64_encode($encrypted);
	}

	/**
	 * @param $data
	 * @param $privateKey
	 *
	 * @return bool|string
	 */
	public static function decryptRsa($data, $privateKey)
	{
		$data = base64_decode($data);
		$key = openssl_pkey_get_private($privateKey);

		$decrypted = '';
		if (!openssl_private_decrypt($data, $decrypted, $key)) {
			return false;
		}
		openssl_pkey_free($key);

		return $decrypted;
	}

	/**
	 * @param $data
	 * @param $privateKey
	 *
	 * @return bool|string
	 */
	public static function signatureRsa($data, $privateKey)
	{
		$key = openssl_pkey_get_private($privateKey);
		if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
			return false;
		}

		return base64_encode($signature);
	}
}