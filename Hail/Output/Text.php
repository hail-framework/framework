<?php
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