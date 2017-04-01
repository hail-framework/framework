<?php
namespace Hail\Output;

use Hail\Facade\Template as Latte;

class Template
{
	/**
	 * @param string $name
	 * @param array $params
	 */
	public function send($name, $params)
	{
		Latte::render($name . '.latte', $params);
	}
}