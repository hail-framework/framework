<?php
namespace Hail\Latte\Exception;


/**
 * The exception occured during Latte compilation.
 */
class CompileException extends \Exception
{
	/** @var string */
	public $sourceCode;

	/** @var string */
	public $sourceName;

	/** @var int */
	public $sourceLine;


	public function setSource(string $code, int $line, string $name = NULL)
	{
		$this->sourceCode = $code;
		$this->sourceLine = $line;
		$this->sourceName = $name;
		if (@is_file($name)) { // @ - may trigger error
			$this->message = rtrim($this->message, '.')
				. ' in ' . str_replace(dirname(dirname($name)), '...', $name) . ($line ? ":$line" : '');
		}
		return $this;
	}

}