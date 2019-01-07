<?php

namespace Hail\Facade;

use Hail\Crypto\Raw;
use Hail\Crypto\Hash\{
    Password, Hash, Hmac
};
use Hail\Crypto\Encryption\{
    RSA, AES256CTR, AES256GCM
};

/**
 * Class Crypto
 *
 * @package Hail\Facade
 * @method static Raw encrypt(string $plaintext, string $key)
 * @method static Raw encryptWithPassword(string $plaintext, string $password)
 * @method static string decrypt(string $cipherText, string $key)
 * @method static string decryptWithPassword(string $cipherText, string $password)
 * @method static Password  password()
 * @method static Hash      hash()
 * @method static Hmac      hamc()
 * @method static Rsa       rsa()
 * @method static AES256CTR aes256ctr()
 * @method static AES256GCM aes256gcm()
 */
class Crypto extends Facade
{

}