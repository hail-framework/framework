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
	const TYPE = [
		'json' => 'Json',
		'jsonp' => 'JsonP',
		'file' => 'File',
		'text' => 'Text',
	];

	private $output = false;

	public function __get($name)
	{
		if ($this->output) {
			throw new \RuntimeException("Response Already Output");
		}
		$this->output = true;

		$type = self::TYPE;
		if (!isset($type[$name])) {
			throw new \RuntimeException('Output Type Node Defined');
		}

		$class = __NAMESPACE__ . '\\Output\\' . $type[$name];
		return new $class();
	}
}