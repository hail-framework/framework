<?php

namespace Hail\Facade;

use Hail;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Browser
 *
 * @package Hail\Facade
 *
 * @method static ResponseInterface get(string $url, array $params = [], array $headers = [])
 * @method static ResponseInterface post(string $url, array $params = [], array $headers = [])
 * @method static ResponseInterface socket(string $url, string $content)
 * @method static string json(string $url, string $content)
 * @method static void timeout(int $seconds)
 */
class Browser extends Facade
{
}