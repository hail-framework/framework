<?php
namespace Hail\Console\Input;

use DateTime;
use SplFileInfo;

class ValueType
{
	const BOOLEAN = 'boolean';
	const DATETIME = 'dateTime';
	const DATE = 'date';
	const DIR = 'dir';
	const FILE = 'file';
	const PATH = 'path';
	const EMAIL = 'email';
	const IP = 'ip';
	const IPV4 = 'ipv4';
	const IPV6 = 'ipv6';
	const NUMBER = 'number';
	const STRING = 'string';
	const URL = 'url';
	const REGEX = 'regex';

	protected $testFun;

	/**
	 * Type option.
	 *
	 * @var string
	 */
	protected $option;
	protected $parsed;
	protected $file = false;

	public $matches = [];

	public function __construct($type, string $option = null)
	{
		if ($type === self::DATETIME) {
			$option = $option ?? DateTime::ATOM;
		}

		$this->testFun = $type . 'Test';
		if (!method_exists($this, $this->testFun)) {
			throw new \InvalidArgumentException("Type '$type' not defined.");
		}

		if ($option) {
			$this->option = $option;
		}
	}

	public function test($value)
	{
		$fn = $this->testFun;
		return $this->$fn($value);
	}

	public function parse($value)
	{
		if ($this->file) {
			return new SplFileInfo($value);
		}

		return $this->parsed;
	}

	protected function booleanTest($value)
	{
		if (is_string($value)) {
			$value = strtolower($value);
			if ($value === '0' || $value === 'false') {
				$this->parsed = false;
				return true;
			}

			if ($value === '1' || $value === 'true') {
				$this->parsed = true;
				return true;
			}
		} else if (is_bool($value)) {
			$this->parsed = $value;
			return true;
		}

		return false;
	}

	protected function dateTimeTest($value)
	{
		$this->parsed = $value = DateTime::createFromFormat($this->option, $value);
		return $value !== false;
	}

	protected function dateTest($value)
	{
		$this->parsed = $a = date_parse($value);
		return !($a === false || $a['error_count'] > 0);
	}

	protected function dirTest($value)
	{
		return $this->file = is_dir($value);
	}

	protected function fileTest($value)
	{
		return $this->file = is_file($value);
	}

	protected function emailTest($value)
	{
		$this->parsed = $value = (string) $value;
		return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
	}

	protected function ipTest($value)
	{
		$this->parsed = $value = (string) $value;
		return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
	}

	protected function ipv4Test($value)
	{
		$this->parsed = $value = (string) $value;
		return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
	}

	protected function ipv6Test($value)
	{
		$this->parsed = $value = (string) $value;
		return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
	}

	protected function numberTest($value)
	{
		$this->parsed = (int) $value;
		return (string) $this->parsed === (string) $value;
	}

	protected function pathTest($value)
	{
		return $this->file = file_exists($value);
	}

	protected function regexTest($value)
	{
		$this->parsed = $value = (string) $value;
		return preg_match($this->option, $value, $this->matches) !== 0;
	}

	protected function stringTest($value)
	{
		$this->parsed = (string) $value;
		return is_string($value);
	}

	protected function urlTest($value)
	{
		$this->parsed = $value = (string) $value;
		return filter_var($value, FILTER_VALIDATE_URL) !== false;
	}
}