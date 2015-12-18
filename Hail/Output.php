<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
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
 */
class Output
{
	public function __get($name)
	{
		return $this->$name();
	}

	public function json() {
		return new Output\Json();
	}


	public function jsonp() {
		return new Output\JsonP();
	}

	public function file() {
		return new Output\File();
	}

	public function text() {
		return new Output\Text();
	}
}