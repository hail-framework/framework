<?php

namespace Hail\Crypto;


class Raw
{
    public const FORMAT_HEX = 'hex';
    public const FORMAT_STR = 'str';
    public const FORMAT_BASE64 = 'base64';

    /**
     * @var string
     */
    private $raw;

    public function __construct(string $raw, string $format = null)
    {
        switch ($format) {
            case self::FORMAT_HEX:
                $this->raw = \hex2bin($raw);
                break;

            case self::FORMAT_BASE64:
                $this->raw = \base64_decode($raw);
                break;

            case self::FORMAT_STR:
                $this->raw = \hex2bin(\str_replace('\x', '', $raw));
                break;

            default:
                $this->raw = $raw;
        }
    }

    public function __toString(): string
    {
        return $this->raw;
    }

    public function hex(): string
    {
        return \bin2hex($this->raw);
    }

    public function base64(): string
    {
        return \base64_encode($this->raw);
    }

    public function str(): string
    {
        $field = \strtoupper(\bin2hex($this->raw));
        $field = \chunk_split($field, 2, '\x');

        return '\x' . \substr($field, 0, -2);
    }
}