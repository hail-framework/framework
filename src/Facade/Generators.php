<?php

namespace Hail\Facade;

/**
 * Class Generators
 *
 * @package Hail\Facade
 * @see     \Hail\Util\Arrays
 *
 * @method static string random(int $length = 10, string $charList = '0-9a-zA-Z')
 * @method static string unique()
 * @method static string guid()
 * @method static string uuid3(string $namespace, string $name)
 * @method static string uuid4()
 * @method static string uuid5(string $namespace, string $name)
 * @method static bool isUUID(string $uuid)
 * @method static string getBytes(string $uuid)
 */
class Generators extends Facade
{

}