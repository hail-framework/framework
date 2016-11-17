<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 11:47
 */

namespace Hail\Output;

class Text
{
	/**
	 * @param mixed $source
	 */
	public function send($source)
	{
		echo $source;
	}
}