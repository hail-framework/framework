<?php

namespace Hail\Facade;

/**
 * Class Serialize
 *
 * @package Hail\Facade
 *
 * @method static $this type(string $type)
 * @method static $this once(string $type)
 * @method static string encode(mixed $value)
 * @method static mixed decode(string $value)
 * @method static string encodeToBase64(mixed $value)
 * @method static mixed decodeFromBase64(string $value)
 * @method static array encodeArray(array $value)
 * @method static array decodeArray(array $value)
 * @method static array encodeArrayToBase64(array $value)
 * @method static array decodeArrayFromBase64(array $value)
 */
class Serialize extends Facade
{
}