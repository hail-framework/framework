<?php

namespace Hail\Facade;

/**
 * Class Serialize
 *
 * @package Hail\Facade
 * @see \Hail\Serialize\Adapter\Json
 *
 * @method static string encode(mixed $value, int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
 * @method static mixed decode(string $json, bool $asArray = true)
 */
class Json extends Facade
{
}