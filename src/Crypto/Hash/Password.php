<?php

namespace Hail\Crypto\Hash;


class Password
{
    private $algo;

    public function __construct(int $algo = null)
    {
        $this->algo = $algo ?? \PASSWORD_DEFAULT;
    }

    /**
     * @param string $password
     *
     * @return string|null
     */
    public function hash(string $password): ?string
    {
        $hash = \password_hash($password, $this->algo);
        if ($hash === false) {
            return null;
        }

        return $hash;
    }

    /**
     * @param $password
     * @param $hash
     *
     * @return bool|string
     */
    public function verify(string $password, string $hash)
    {
        if (\password_verify($password, $hash)) {
            if (\password_needs_rehash($hash, $this->algo)) {
                return $this->hash($password);
            }

            return true;
        }

        return false;
    }
}