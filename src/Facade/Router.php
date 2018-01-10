<?php
namespace Hail\Facade;

/**
 * Class Router
 *
 * @package Hail\Facade
 *
 * @method static void addRoute(array $methods, string $route, array $handler)
 * @method static array|null getOptions(string $url)
 * @method static array dispatch(string $method, string $url)
 * @method static array getResult()
 * @method static array getRoutes()
 * @method static void options(string $route, array $handler)
 * @method static void get(string $route, array $handler)
 * @method static void head(string $route, array $handler)
 * @method static void post(string $route, array $handler)
 * @method static void put(string $route, array $handler)
 * @method static void delete(string $route, array $handler)
 * @method static void trace(string $route, array $handler)
 * @method static void connect(string $route, array $handler)
 */
class Router extends Facade
{
}