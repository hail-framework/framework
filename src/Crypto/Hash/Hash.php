<?php

namespace Hail\Crypto\Hash;

use Hail\Crypto\Raw;

class Hash
{
    private $type;

    public function __construct(string $type = null)
    {
        $this->type = $type ?? 'sha256';
    }

    public function hash(string $text): Raw
    {
        $raw = \hash($this->type, $text, true);

        return new Raw($raw);
    }

    public function verify(string $hash, string $text): bool
    {
        return \hash_equals($hash, (string) $this->hash($text));
    }
}