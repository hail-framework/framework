<?php

namespace Hail\Facade;

use Hail;

/**
 * Class Browser
 *
 * @package Hail\Facade
 *
 * @method static Hail\Browser\Response get(string $url, array $params = [], array $headers = [])
 * @method static Hail\Browser\Response post(string $url, array $params = [], array $headers = [])
 * @method static Hail\Browser\Response socket(string $url, string $content)
 * @method static Hail\Browser\Response json(string $url, array $params = [], array $headers = [])
 * @method static Hail\Browser\Response head(string $url, array $headers = [])
 * @method static Hail\Browser\Response patch(string $url, array $headers = [], string $body = null)
 * @method static Hail\Browser\Response put(string $url, array $headers = [], string $body = null)
 * @method static int timeout(int $seconds)
 */
class Browser extends Facade
{
	protected static function instance()
	{
		return new Hail\Browser();
	}
}