<?php

namespace Hail\Facade;

use Psr\Http\Message\{
    UploadedFileInterface,
    UriInterface
};

/**
 * Class Request
 *
 * @package Hail\Facade
 * @see \Hail\Http\Request
 *
 * @method static array inputs(array $value = null)
 * @method static mixed input(string $name, $value = null)
 * @method static UploadedFileInterface[] files()
 * @method static UploadedFileInterface|NULL file(string $name)
 * @method static mixed cookie(string $name)
 * @method static string method()
 * @method static string target()
 * @method static string protocol()
 * @method static string|null server(string $name)
 * @method static string|null header(string $name)
 * @method static mixed attribute(string $name)
 * @method static UriInterface uri()
 * @method static bool secure()
 */
class Request extends Facade
{
}