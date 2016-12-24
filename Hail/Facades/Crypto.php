<?php
namespace Hail\Facades;

/**
 * Class Serialize
 *
 * @package Hail\Facades
 *
 * @method static \Hail\Util\Crypto setFormat(string $format)
 * @method static \Hail\Util\Crypto withFormat(string $format)
 * @method static bool|string password(string $password)
 * @method static bool|string verifyPassword(string $password, string $hash)
 * @method static string hash(string $text)
 * @method static bool verifyHash(string $hash, string $text)
 * @method static string hmac(string $text, string $salt)
 * @method static bool verifyHMAC(string $hash, string $text, string $salt)
 * @method static string createKey()
 * @method static string encrypt(string $plaintext, string $key, $password = false)
 * @method static string encryptWithPassword(string $plaintext, string $key)
 * @method static string decrypt(string $cipherText, string $key, $password = false)
 * @method static string decryptWithPassword(string $cipherText, string $password)
 * @method static string fromBin(string $raw, string $format = null)
 * @method static string hkdf(string $hash, string $ikm, int $length, $info = '', $salt = null)
 * @method static string pbkdf2(string $algorithm, string $password, string $salt, int $count, int $length, bool $raw = false)
 * @method static string encryptRsaPublic(string $data, string $publicKey)
 * @method static string decryptRsaPrivate(string $data, string $privateKey)
 * @method static string encryptRsaPrivate(string $data, string $privateKey)
 * @method static string decryptRsaPublic(string $data, string $publicKey)
 * @method static string signatureRsa(string $data, string $privateKey)
 * @method static bool verifyRsa(string $data, string $signature, string $publicKey)
 */
class Crypto extends Facade
{
	protected static function instance()
	{
		return new \Hail\Util\Crypto(
			Config::get('crypto.format')
		);
	}
}