<?php
namespace Hail\Exception;

use Exception;

class ShellException extends Exception
{
	public function __toString()
	{
		$error = "[{$this->code}]: {$this->message}\n";
		if (defined('SHELL_WRAP_INTERACTIVE')) {
			echo $error;
		} else {
			return $error;
		}
	}
}