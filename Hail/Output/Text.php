<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 11:47
 */

namespace Hail\Output;


use Hail\DITrait;

class Text
{
	use DITrait;

	/**
	 * @param mixed $source
	 */
	public function send($source)
	{
		echo $source;
	}
}