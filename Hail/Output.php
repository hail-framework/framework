<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 12:10
 */

namespace Hail;


/**
 * Class Output
 * @package Hail
 * @property-read Output\Json $json
 * @property-read Output\JsonP $jsonp
 * @property-read Output\File $file
 * @property-read Output\Text $text
 * @property-read Output\Template $template
 */
class Output
{
	private $output = false;

	public function __get($name)
	{
		if ($this->output) {
			throw new \RuntimeException("Response Already Output");
		}
		$this->output = true;

		$class = __NAMESPACE__ . '\\Output\\' . ucfirst($name);
		return new $class();
	}
}