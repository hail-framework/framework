<?php
/*
 * This file is part of the GetOptionKit package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Hail\Console\Input;

use InvalidArgumentException;
use Hail\Console\Exception\InvalidOptionValueException;

class Option
{
	public $short;

	public $long;

	/**
	 * @var string the description of this option
	 */
	public $desc;

	/**
	 * @var string The option key
	 */
	public $key;  /* key to store values */

	public $value;

	public $type;

	public $valueName; /* name for the value place holder, for printing */

	public $isa;

	public $isaOption;

	public $validValues;

	public $suggestions;

	public $defaultValue;

	public $incremental = false;

	/**
	 * @var \Closure The filter closure of the option value.
	 */
	public $filter;

	public $validator;

	public $multiple = false;

	public $optional = false;

	public $required = false;

	public $flag = false;

	/**
	 * @var callable trigger callback after value is set.
	 */
	protected $trigger;

	public function __construct($spec)
	{
		$this->initFromSpecString($spec);
	}

	/**
	 * Build spec attributes from spec string.
	 *
	 * @param string $specString
	 *
	 * @throws \LogicException
	 */
	protected function initFromSpecString($specString)
	{
		$pattern = '/
        (
                (?:[a-zA-Z0-9-]+)
                (?:
                    \|
                    (?:[a-zA-Z0-9-]+)
                )?
        )

        # option attribute operators
        ([:+?])?

        # value types
        (?:=(boolean|string|number|date|file|dir|url|email|ip|ipv6|ipv4))?
        /x';
		$ret = preg_match($pattern, $specString, $regs);
		if ($ret === false || $ret === 0) {
			throw new \LogicException('Incorrect spec string');
		}

		$name = $regs[1];
		$attributes = $regs[2] ?? null;
		$type = $regs[3] ?? null;

		$short = null;
		$long = null;

		// check long,short option name.
		if (strpos($name, '|') !== false) {
			[$short, $long] = explode('|', $name);
		} else if (strlen($name) === 1) {
			$short = $name;
		} else if (strlen($name) > 1) {
			$long = $name;
		}

		$this->short = $short;
		$this->long = $long;

		// option is required.
		if (strpos($attributes, ':') !== false) {
			$this->required();
		} else if (strpos($attributes, '+') !== false) {
			// option with multiple value
			$this->multiple();
		} else if (strpos($attributes, '?') !== false) {
			// option is optional.(zero or one value)
			$this->optional();
		} else {
			$this->flag();
		}

		if ($type) {
			$this->isa($type);
		}
	}

	/**
	 * get the option key for result key mapping.
	 */
	public function getId()
	{
		if ($this->key) {
			return $this->key;
		}

		return $this->long ?: $this->short;
	}

	/**
	 * To make -v, -vv, -vvv works.
	 */
	public function incremental()
	{
		$this->incremental = true;

		return $this;
	}

	public function required()
	{
		$this->required = true;

		return $this;
	}

	public function defaultValue($value)
	{
		$this->defaultValue = $value;

		return $this;
	}

	public function multiple()
	{
		$this->multiple = true;
		$this->value = [];  # for value pushing
		return $this;
	}

	public function optional()
	{
		$this->optional = true;

		return $this;
	}

	public function flag()
	{
		$this->flag = true;

		return $this;
	}

	public function trigger(\Closure $trigger)
	{
		$this->trigger = $trigger;

		return $this;
	}

	public function isIncremental()
	{
		return $this->incremental;
	}

	public function isFlag()
	{
		return $this->flag;
	}

	public function isMultiple()
	{
		return $this->multiple;
	}

	public function isRequired()
	{
		return $this->required;
	}

	public function isOptional()
	{
		return $this->optional;
	}

	public function isTypeNumber()
	{
		return $this->isa === ValueType::NUMBER;
	}

	public function isType($type)
	{
		return $this->isa === $type;
	}

	public function getTypeClass()
	{
		return new ValueType($this->isa, $this->isaOption);
	}

	public function testValue($value)
	{
		$type = $this->getTypeClass();

		return $type->test($value);
	}

	protected function _preprocessValue($value)
	{
		$val = $value;

		if ($isa = $this->isa) {
			$type = $this->getTypeClass();
			if ($type->test($value)) {
				$val = $type->parse($value);
			} else {
				throw new InvalidOptionValueException("Invalid value for {$this->renderReadableSpec(false)}. Requires a type $isa.");
			}
		}

		// check pre-filter for option value
		if ($this->filter) {
			$val = ($this->filter)($val);
		}

		// check validValues
		if (($validValues = $this->getValidValues()) && !in_array($value, $validValues, true)) {
			throw new InvalidOptionValueException('valid values are: ' . implode(', ', $validValues));
		}

		if (!$this->validate($value)[0]) {
			throw new InvalidOptionValueException('option is invalid');
		}

		return $val;
	}

	protected function callTrigger()
	{
		if ($this->trigger && ($ret = ($this->trigger)($this->value))) {
			$this->value = $ret;
		}
	}

	/*
	 * set option value
	 */
	public function setValue($value)
	{
		$this->value = $this->_preprocessValue($value);
		$this->callTrigger();
	}

	/**
	 * This method is for incremental option.
	 */
	public function increaseValue()
	{
		if (!$this->value) {
			$this->value = 1;
		} else {
			++$this->value;
		}
		$this->callTrigger();
	}

	/**
	 * push option value, when the option accept multiple values.
	 *
	 * @param mixed
	 */
	public function pushValue($value)
	{
		$value = $this->_preprocessValue($value);
		$this->value[] = $value;
		$this->callTrigger();
	}

	public function desc($desc)
	{
		$this->desc = $desc;
	}

	/**
	 * valueName is for option value hinting:.
	 *
	 *   --name=<name>
	 */
	public function valueName($name)
	{
		$this->valueName = $name;

		return $this;
	}

	public function renderValueHint()
	{
		$n = null;
		if ($this->valueName) {
			$n = $this->valueName;
		} else if ($values = $this->getValidValues()) {
			$n = '(' . implode(',', $values) . ')';
		} else if ($values = $this->getSuggestions()) {
			$n = '[' . implode(',', $values) . ']';
		} else if ($val = $this->defaultValue) {
			// This allows for `0` and `false` values to be displayed also.
			if (is_bool($val)) {
				$n = ($val ? 'true' : 'false');
			} elseif (is_scalar($val) && ((string) $val) !== '') {
				$n = $val;
			}
		}

		if (!$n && $this->isa !== null) {
			$n = '<' . $this->isa . '>';
		}
		if ($this->isRequired()) {
			return "=$n";
		} else if ($this->defaultValue || $this->isOptional()) {
			return "[=$n]";
		} else if ($n) {
			return "=$n";
		}

		return '';
	}

	public function getValue()
	{
		return $this->value ?? $this->defaultValue;
	}

	/**
	 * get readable spec for printing.
	 *
	 * @param bool $renderHint
	 *
	 * @return string
	 */
	public function renderReadableSpec($renderHint = true)
	{
		$c1 = '';
		if ($this->short && $this->long) {
			$c1 = "-{$this->short}, --{$this->long}";
		} else if ($this->short) {
			$c1 = "-{$this->short}";
		} else if ($this->long) {
			$c1 = "--{$this->long}";
		}
		if ($renderHint) {
			return $c1 . $this->renderValueHint();
		}

		return $c1;
	}

	public function __toString()
	{
		$c1 = $this->renderReadableSpec();
		$return = '* key:' . str_pad($this->getId(), 8) . " spec:$c1  desc:{$this->desc}\n";
		$val = $this->getValue();
		if (is_array($val)) {
			$return .= '  value => ' . implode(',', array_map(function ($v) {
					return var_export($v, true);
				}, $val)) . "\n";
		} else {
			$return .= "  value => $val\n";
		}

		return $return;
	}

	/**
	 * Value Type Setters.
	 *
	 * @param string $type   the value type, valid values are 'number', 'string',
	 *                       'file', 'boolean', you can also use your own value type name.
	 * @param mixed  $option option(s) for value type class (optionnal)
	 *
	 * @return $this
	 */
	protected function isa($type, $option = null)
	{
		$this->isa = $type;
		$this->isaOption = $option;

		return $this;
	}

	public function isString()
	{
		return $this->isa(ValueType::STRING);
	}

	public function isBoolean()
	{
		return $this->isa(ValueType::BOOLEAN);
	}

	public function isDateTime(string $option = null)
	{
		return $this->isa(ValueType::DATETIME, $option);
	}

	public function isDate()
	{
		return $this->isa(ValueType::DATE);
	}

	public function isDir()
	{
		return $this->isa(ValueType::DIR);
	}

	public function isFile()
	{
		return $this->isa(ValueType::FILE);
	}

	public function isPath()
	{
		return $this->isa(ValueType::PATH);
	}

	public function isEmail()
	{
		return $this->isa(ValueType::EMAIL);
	}

	public function isIp()
	{
		return $this->isa(ValueType::IP);
	}

	public function isIpv4()
	{
		return $this->isa(ValueType::IPV4);
	}

	public function isIpv6()
	{
		return $this->isa(ValueType::IPV6);
	}

	public function isNumber()
	{
		return $this->isa(ValueType::NUMBER);
	}

	public function isUrl()
	{
		return $this->isa(ValueType::URL);
	}

	public function isRegex(string $option)
	{
		return $this->isa(ValueType::REGEX, $option);
	}

	/**
	 * Assign validValues to member value.
	 */
	public function validValues($values)
	{
		$this->validValues = $values;

		return $this;
	}

	/**
	 * Assign suggestions.
	 *
	 * @param Closure|array
	 *
	 * @return $this
	 */
	public function suggestions($suggestions)
	{
		$this->suggestions = $suggestions;

		return $this;
	}

	/**
	 * Return valud values array.
	 *
	 * @return string[] or nil
	 */
	public function getValidValues()
	{
		if ($this->validValues) {
			if ($this->validValues instanceof \Closure) {
				return ($this->validValues)();
			}

			return $this->validValues;
		}

		return null;
	}

	/**
	 * Return suggestions.
	 *
	 * @return string[] or nil
	 */
	public function getSuggestions()
	{
		if ($this->suggestions) {
			if ($this->suggestions instanceof \Closure) {
				return ($this->suggestions)();
			}

			return $this->suggestions;
		}

		return null;
	}

	public function validate($value)
	{
		if ($this->validator) {
			$ret = ($this->validator)($value);
			if (is_array($ret)) {
				return $ret;
			} elseif ($ret === false) {
				return [false, "Invalid value: $value"];
			} elseif ($ret === true) {
				return [true, 'Successfully validated.'];
			}
			throw new InvalidArgumentException('Invalid return value from the validator.');
		}

		return [true];
	}

	/**
	 * @param \Closure $cb
	 *
	 * @return $this
	 */
	public function validator(\Closure $cb)
	{
		$this->validator = $cb;

		return $this;
	}

	/**
	 * Set up a filter function for the option value.
	 *
	 * @param \Closure $cb
	 *
	 * @return $this
	 */
	public function filter(\Closure $cb)
	{
		$this->filter = $cb;

		return $this;
	}
}
