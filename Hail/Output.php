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
 *
 * @package Hail
 * @property-read Output\Json $json
 * @property-read Output\Jsonp $jsonp
 * @property-read Output\File $file
 * @property-read Output\Text $text
 * @property-read Output\Template $template
 * @property-read Output\Redirect $redirect
 */
class Output
{
	private $output = false;

	public function __get($name)
	{
		return $this->get($name);
	}

	public function get($name)
	{
		if ($this->output) {
			throw new \RuntimeException('Response Already Output');
		}
		$this->output = true;

		$class = __NAMESPACE__ . '\\Output\\' . ucfirst($name);

		return new $class();
	}

	public function json()
	{
		return $this->get('json');
	}

	public function jsonp()
	{
		return $this->get('jsonp');
	}

	public function file()
	{
		return $this->get('file');
	}

	public function text()
	{
		return $this->get('text');
	}

	public function template()
	{
		return $this->get('template');
	}

	public function redirect()
	{
		return $this->get('redirect');
	}
}