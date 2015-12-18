<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
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
		if (is_object($source)) {
			$source->render();
		} else {
			echo $source;
		}
	}
}