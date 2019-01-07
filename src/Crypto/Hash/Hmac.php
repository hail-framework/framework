<?php

namespace Hail\Crypto\Hash;

use Hail\Crypto\Raw;

class Hmac
{
    private $type;

    public function __construct(string $type = null)
    {
        $this->type = $type ?? 'sha256';
    }

    public function hash(string $text, string $salt): Raw
    {
        $raw = \hash_hmac($this->type, $text, $salt, true);

        return new Raw($raw);
    }

    public function verify(string $hash, string $text, string $salt): bool
    {
        return \hash_equals($hash, (string) $this->hash($text, $salt));
    }
}