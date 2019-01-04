<?php

namespace Hail\Facade;

/**
 * Class Serialize
 *
 * @package Hail\Facade
 * @see \Hail\Serialize\Adapter\Yaml
 *
 * @method static string encode(mixed $value, int $inline = 2, int $indent = 0)
 * @method static mixed decode(string $value)
 * @method static mixed decodeFile(string $file)
 */
class Yaml extends Facade
{
}