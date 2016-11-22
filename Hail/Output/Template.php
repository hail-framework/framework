<?php
namespace Hail\Output;

use Hail\Facades\Template as Tpl;

class Template
{
	/**
	 * @param string $name
	 * @param array $params
	 */
	public function send($name, $params)
	{
		Tpl::render($name . '.latte', $params);
	}
}