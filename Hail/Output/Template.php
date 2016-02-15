<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 11:47
 */

namespace Hail\Output;


use Hail\DITrait;

class Template
{
	use DITrait;

	/**
	 * @param string $name
	 * @param array $params
	 */
	public function send($name, $params)
	{
		$this->template->render($name . '.latte', $params);
	}
}