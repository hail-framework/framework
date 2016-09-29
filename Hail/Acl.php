<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/13 0013
 * Time: 23:06
 */

namespace Hail;

use Hail\Utils\Json;

/**
 * Class Acl
 *
 * @package Hail
 */
class Acl
{
	use DITrait;

	public function crypt($password, $salt = null)
	{
		$password = hash_hmac('sha256', $password, $salt, true);
		return base64_encode($password);
	}

	public function relaySig($data)
	{
		ksort($data);
		$salt = $this->config->get('crypt.relay_sig');
		return $this->crypt(Json::encode($data), $salt);
	}

	/**
	 * Encrypt and authenticate
	 *
	 * @param string $data
	 * @param string $key
	 * @return string
	 */
	public function encrypt($data, $key)
	{
		$iv = openssl_random_pseudo_bytes(
			openssl_cipher_iv_length('AES-256-CBC')
		);

		// Encryption
		$encrypted = $this->encryptAes($data,
			mb_substr($key, 0, 32, '8bit'), $iv
		);

		// Authentication
		$hmac = hash_hmac(
			'SHA256',
			$iv . $encrypted,
			mb_substr($key, 32, null, '8bit'),
			true
		);
		return base64_encode($hmac . $iv . $encrypted);
	}

	/**
	 * Authenticate and decrypt
	 *
	 * @param string $data
	 * @param string $key
	 * @return string
	 * @throws \RuntimeException
	 */
	public function decrypt($data, $key)
	{
		$data = base64_decode($data);
		$hmac = mb_substr($data, 0, 32, '8bit');
		$iv = mb_substr($data, 32, 16, '8bit');
		$cipherText = mb_substr($data, 48, null, '8bit');

		// Authentication
		$hmacNew = hash_hmac(
			'SHA256',
			$iv . $cipherText,
			mb_substr($key, 32, null, '8bit'),
			true
		);
		if (!hash_equals((string) $hmac, (string) $hmacNew)) {
			throw new \RuntimeException('Authentication failed');
		}

		// Decrypt
		return $this->decryptAes($cipherText,
			mb_substr($key, 0, 32, '8bit'),
			$iv
		);
	}

	/**
	 * @param string $data
	 * @param string $key
	 * @param string|null $iv
	 *
	 * @return string
	 */
	public function encryptAes($data, $key, $iv = null)
	{
		if ($iv === null) {
			$key = base64_decode($key);
			$iv = mb_substr($key, 32, 16, '8bit');
			$key = mb_substr($key, 0, 32, '8bit');
		}

		return openssl_encrypt(
			$data,
			'AES-256-CBC',
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);
	}

	/**
	 * @param string $data
	 * @param string $key
	 * @param string|null $iv
	 *
	 * @return string
	 */
	public function decryptAes($data, $key, $iv = null)
	{
		if ($iv === null) {
			$key = base64_decode($key);
			$iv = mb_substr($key, 32, 16, '8bit');
			$key = mb_substr($key, 0, 32, '8bit');
		}

		return openssl_decrypt(
			$data,
			'AES-256-CBC',
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);
	}

	public function encryptRsa($data, $publicKey)
	{
		$key = openssl_pkey_get_public($publicKey);

		$encrypted = '';
		if (!openssl_public_encrypt($data, $encrypted, $key)) {
			return false;
		}
		openssl_pkey_free($key);

		return base64_encode($encrypted);
	}

	public function decryptRsa($data, $privateKey)
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

	public function rsaSignature($data, $privateKey)
	{
		$key = openssl_pkey_get_private($privateKey);
		if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
			return false;
		}
		return base64_encode($signature);
	}
}