<?php
namespace Hail\Util;

use InvalidArgumentException;

/**
 * Validates input against certain criteria
 *
 * @package Hail\Util
 * @author  FlyingHail <flyinghail@msn.com>
 *
 */
class Validator
{
	use SingletonTrait;

	/**
	 * @var string
	 */
	const ERROR_DEFAULT = 'Invalid';

	/**
	 * @var array
	 */
	protected $_fields = [];

	/**
	 * @var array
	 */
	protected $_errors = [];

	/**
	 * @var array
	 */
	protected $_validations = [];

	/**
	 * @var array
	 */
	protected $_labels = [];

	/**
	 * Contains all rules that are available to the current valitron instance.
	 *
	 * @var array
	 */
	protected $_instanceRules = [];

	/**
	 * Contains all rule messages that are available to the current valitron
	 * instance
	 *
	 * @var array
	 */
	protected $_instanceRuleMessage = [];

	/**
	 * @var array
	 */
	protected static $_rules = [];

	/**
	 * @var array
	 */
	protected static $_ruleMessages = [];

	/**
	 * @var array
	 */
	protected static $validUrlPrefixes = ['http://', 'https://', 'ftp://'];

	/**
	 * @inheritdoc
	 */
	protected function init()
	{
		static::$_ruleMessages = [
			'required' => _('不能为空'),
			'equals' => _('必须和 "%s" 一致'),
			'different' => _('必须和 "%s" 不一致'),
			'accepted' => _('必须接受'),
			'numeric' => _('只能是数字'),
			'integer' => _('只能是整数(0-9)'),
			'length' => _('长度必须大于 %d'),
			'min' => _('必须大于 %s'),
			'max' => _('必须小于 %s'),
			'in' => _('无效的值'),
			'notIn' => _('无效的值'),
			'ip' => _('无效IP地址'),
			'email' => _('无效邮箱地址'),
			'url' => _('无效的URL'),
			'urlActive' => _('必须是可用的域名'),
			'alpha' => _('只能包括英文字母(a-z)'),
			'alphaNum' => _('只能包括英文字母(a-z)和数字(0-9)'),
			'slug' => _('只能包括英文字母(a-z)、数字(0-9)、破折号和下划线'),
			'regex' => _('无效格式'),
			'date' => _('无效的日期'),
			'dateFormat' => _('日期的格式应该为 "%s"'),
			'dateBefore' => _('日期必须在 "%s" 之前'),
			'dateAfter' => _('日期必须在 "%s" 之后'),
			'contains' => _('必须包含 %s'),
		];
	}

	/**
	 * Required field validator
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateRequired($field, $value)
	{
		if (null === $value) {
			return false;
		} elseif (is_string($value) && trim($value) === '') {
			return false;
		}

		return true;
	}

	/**
	 * Validate that two values match
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateEquals($field, $value, array $params)
	{
		$field2 = $params[0];

		return isset($this->_fields[$field2]) && $value === $this->_fields[$field2];
	}

	/**
	 * Validate that a field is different from another field
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateDifferent($field, $value, array $params)
	{
		$field2 = $params[0];

		return isset($this->_fields[$field2]) && $value !== $this->_fields[$field2];
	}

	/**
	 * Validate that a field was "accepted" (based on PHP's string evaluation rules)
	 *
	 * This validation rule implies the field is "required"
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateAccepted($field, $value)
	{
		$acceptable = ['yes', 'on', 1, '1', true];

		return $this->validateRequired($field, $value) && in_array($value, $acceptable, true);
	}

	/**
	 * Validate that a field is an array
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateArray($field, $value)
	{
		return is_array($value);
	}

	/**
	 * Validate that a field is numeric
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateNumeric($field, $value)
	{
		return is_numeric($value);
	}

	/**
	 * Validate that a field is an integer
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateInteger($field, $value)
	{
		return filter_var($value, \FILTER_VALIDATE_INT) !== false;
	}

	/**
	 * Validate the length of a string
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateLength($field, $value, $params)
	{
		$length = $this->stringLength($value);
		// Length between
		if (isset($params[1])) {
			return $length >= $params[0] && $length <= $params[1];
		}

		// Length same
		return ($length !== false) && $length == $params[0];
	}

	/**
	 * Validate the length of a string (between)
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @return boolean
	 */
	protected function validateLengthBetween($field, $value, $params)
	{
		$length = $this->stringLength($value);

		return ($length !== false) && $length >= $params[0] && $length <= $params[1];
	}

	/**
	 * Validate the length of a string (min)
	 *
	 * @param string $field
	 * @param mixed  $value
	 * @param array  $params
	 *
	 * @return boolean
	 */
	protected function validateLengthMin($field, $value, $params)
	{
		$length = $this->stringLength($value);

		return ($length !== false) && $length >= $params[0];
	}

	/**
	 * Validate the length of a string (max)
	 *
	 * @param string $field
	 * @param mixed  $value
	 * @param array  $params
	 *
	 * @return boolean
	 */
	protected function validateLengthMax($field, $value, $params)
	{
		$length = $this->stringLength($value);

		return ($length !== false) && $length <= $params[0];
	}

	/**
	 * Get the length of a string
	 *
	 * @param  string $value
	 *
	 * @return int|false
	 */
	protected function stringLength($value)
	{
		if (!is_string($value)) {
			return false;
		}

		return mb_strlen($value);
	}

	/**
	 * Validate the size of a field is greater than a minimum value.
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateMin($field, $value, $params)
	{
		if (!is_numeric($value)) {
			return false;
		} elseif (function_exists('bccomp')) {
			return !(bccomp($params[0], $value, 14) === 1);
		} else {
			return $params[0] <= $value;
		}
	}

	/**
	 * Validate the size of a field is less than a maximum value
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateMax($field, $value, $params)
	{
		if (!is_numeric($value)) {
			return false;
		} elseif (function_exists('bccomp')) {
			return !(bccomp($value, $params[0], 14) === 1);
		} else {
			return $params[0] >= $value;
		}
	}

	/**
	 * Validate the size of a field is between min and max values
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @return bool
	 */
	protected function validateBetween($field, $value, $params)
	{
		if (!is_numeric($value)) {
			return false;
		}
		if (!isset($params[0]) || !is_array($params[0]) || count($params[0]) !== 2) {
			return false;
		}

		list($min, $max) = $params[0];

		return $this->validateMin($field, $value, [$min]) && $this->validateMax($field, $value, [$max]);
	}

	/**
	 * Validate a field is contained within a list of values
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateIn($field, $value, $params)
	{
		$isAssoc = array_values($params[0]) !== $params[0];
		if ($isAssoc) {
			$params[0] = array_keys($params[0]);
		}

		$strict = false;
		if (isset($params[1])) {
			$strict = $params[1];
		}

		return in_array($value, $params[0], $strict);
	}

	/**
	 * Validate a field is not contained within a list of values
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateNotIn($field, $value, $params)
	{
		return !$this->validateIn($field, $value, $params);
	}

	/**
	 * Validate a field contains a given string
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @return bool
	 */
	protected function validateContains($field, $value, $params)
	{
		if (!isset($params[0])) {
			return false;
		}
		if (!is_string($value) || !is_string($params[0])) {
			return false;
		}

		$strict = true;
		if (isset($params[1])) {
			$strict = (bool) $params[1];
		}

		if ($strict) {
			return mb_strpos($value, $params[0]) !== false;
		}

		return mb_stripos($value, $params[0]) !== false;
	}

	/**
	 * Validate that a field is a valid IP address
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateIp($field, $value)
	{
		return filter_var($value, \FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Validate that a field is a valid e-mail address
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateEmail($field, $value)
	{
		return filter_var($value, \FILTER_VALIDATE_EMAIL) !== false;
	}

	/**
	 * Validate that a field is a valid URL by syntax
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateUrl($field, $value)
	{
		foreach (static::$validUrlPrefixes as $prefix) {
			if (strpos($value, $prefix) !== false) {
				return filter_var($value, \FILTER_VALIDATE_URL) !== false;
			}
		}

		return false;
	}

	/**
	 * Validate that a field is an active URL by verifying DNS record
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateUrlActive($field, $value)
	{
		foreach (static::$validUrlPrefixes as $prefix) {
			if (strpos($value, $prefix) !== false) {
				$host = parse_url(strtolower($value), PHP_URL_HOST);

				return checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA') || checkdnsrr($host, 'CNAME');
			}
		}

		return false;
	}

	/**
	 * Validate that a field contains only alphabetic characters
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateAlpha($field, $value)
	{
		return preg_match('/^([a-z])+$/i', $value);
	}

	/**
	 * Validate that a field contains only alpha-numeric characters
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateAlphaNum($field, $value)
	{
		return preg_match('/^([a-z0-9])+$/i', $value);
	}

	/**
	 * Validate that a field contains only alpha-numeric characters, dashes, and underscores
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateSlug($field, $value)
	{
		return preg_match('/^([-a-z0-9_-])+$/i', $value);
	}

	/**
	 * Validate that a field passes a regular expression check
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @return bool
	 */
	protected function validateRegex($field, $value, $params)
	{
		return preg_match($params[0], $value);
	}

	/**
	 * Validate that a field is a valid date
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateDate($field, $value)
	{
		if ($value instanceof \DateTime) {
			return true;
		}

		return strtotime($value) !== false;
	}

	/**
	 * Validate that a field matches a date format
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateDateFormat($field, $value, $params)
	{
		$parsed = date_parse_from_format($params[0], $value);

		return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
	}

	/**
	 * Validate the date is before a given date
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateDateBefore($field, $value, $params)
	{
		$vtime = ($value instanceof \DateTime) ? $value->getTimestamp() : strtotime($value);
		$ptime = ($params[0] instanceof \DateTime) ? $params[0]->getTimestamp() : strtotime($params[0]);

		return $vtime < $ptime;
	}

	/**
	 * Validate the date is after a given date
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @internal param array $fields
	 * @return bool
	 */
	protected function validateDateAfter($field, $value, $params)
	{
		$vtime = ($value instanceof \DateTime) ? $value->getTimestamp() : strtotime($value);
		$ptime = ($params[0] instanceof \DateTime) ? $params[0]->getTimestamp() : strtotime($params[0]);

		return $vtime > $ptime;
	}

	/**
	 * Validate that a field contains a boolean.
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function validateBoolean($field, $value)
	{
		return is_bool($value);
	}

	/**
	 * Validate that a field contains a valid credit card
	 * optionally filtered by an array
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @param  array  $params
	 *
	 * @return bool
	 */
	protected function validateCreditCard($field, $value, $params)
	{
		/**
		 * I there has been an array of valid cards supplied, or the name of the users card
		 * or the name and an array of valid cards
		 */
		if (!empty($params)) {
			/**
			 * array of valid cards
			 */
			if (is_array($params[0])) {
				$cards = $params[0];
			} elseif (is_string($params[0])) {
				$cardType = $params[0];
				if (isset($params[1]) && is_array($params[1])) {
					$cards = $params[1];
					if (!in_array($cardType, $cards, true)) {
						return false;
					}
				}
			}
		}


		if ($this->creditCardNumberIsValid($value)) {
			$cardRegex = [
				'visa' => '#^4[0-9]{12}(?:[0-9]{3})?$#',
				'mastercard' => '#^(5[1-5]|2[2-7])[0-9]{14}$#',
				'amex' => '#^3[47][0-9]{13}$#',
				'dinersclub' => '#^3(?:0[0-5]|[68][0-9])[0-9]{11}$#',
				'discover' => '#^6(?:011|5[0-9]{2})[0-9]{12}$#',
			];

			if (isset($cardType)) {
				// if we don't have any valid cards specified and the card we've been given isn't in our regex array
				if (!isset($cards) && !isset($cardRegex[$cardType])) {
					return false;
				}

				// we only need to test against one card type
				return (preg_match($cardRegex[$cardType], $value) === 1);
			} elseif (isset($cards)) {
				// if we have cards, check our users card against only the ones we have
				foreach ($cards as $card) {
					if (
						isset($cardRegex[$card]) &&
						preg_match($cardRegex[$card], $value) === 1 // if the card is valid, we want to stop looping
					) {
						return true;
					}
				}
			} else {
				// loop through every card
				foreach ($cardRegex as $regex) {
					// until we find a valid one
					if (preg_match($regex, $value) === 1) {
						return true;
					}
				}
			}
		}

		// if we've got this far, the card has passed no validation so it's invalid!
		return false;
	}

	/**
	 * Luhn algorithm
	 *
	 * @param string
	 *
	 * @return bool
	 */
	protected function creditCardNumberIsValid($value)
	{
		$number = preg_replace('/\D+/', '', $value);
		$sum = 0;

		$len = strlen($number);
		if ($len < 13) {
			return false;
		}

		for ($i = 0; $i < $len; $i++) {
			$digit = (int) $number[$len - $i - 1];
			if ($i % 2 === 1) {
				$sub_total = $digit * 2;
				if ($sub_total > 9) {
					$sub_total = ($sub_total - 10) + 1;
				}
			} else {
				$sub_total = $digit;
			}
			$sum += $sub_total;
		}

		return ($sum > 0 && $sum % 10 === 0);
	}

	protected function validateInstanceOf($field, $value, $params)
	{
		if (is_object($value)) {
			if (is_object($params[0]) && $value instanceof $params[0]) {
				return true;
			}

			if (get_class($value) === $params[0]) {
				return true;
			}
		}

		return is_string($value) && is_string($params[0]) && get_class($value) === $params[0];
	}

	//Validate optional field
	protected function validateOptional($field, $value, $params)
	{
		//Always return true
		return true;
	}

	/**
	 * Set data for validator
	 *
	 * @param  array $data
	 * @param  array $fields
	 *
	 * @return $this
	 */
	public function data(array $data, array $fields = [])
	{
		// Allows filtering of used input fields against optional second array of field names allowed
		// This is useful for limiting raw $_POST or $_GET data to only known fields
		$this->_fields = $fields !== [] ? array_intersect_key($data, array_flip($fields)) : $data;

		return $this;
	}

	/**
	 *  Get array of fields and data
	 *
	 * @return array
	 */
	public function getData()
	{
		return $this->_fields;
	}

	/**
	 * Get array of error messages
	 *
	 * @param  null|string $field
	 *
	 * @return array|bool
	 */
	public function errors(string $field = null)
	{
		if ($field !== null) {
			return $this->_errors[$field] ?? false;
		}

		return $this->_errors;
	}

	/**
	 * Add an error to error messages array
	 *
	 * @param string $field
	 * @param string $msg
	 * @param array  $params
	 */
	public function error(string $field, string $msg, array $params = [])
	{
		$msg = $this->checkAndSetLabel($field, $msg, $params);

		$values = [];
		// Printed values need to be in string format
		foreach ($params as $param) {
			if (is_array($param)) {
				$param = "['" . implode("', '", $param) . "']";
			}
			if ($param instanceof \DateTime) {
				$param = $param->format('Y-m-d');
			} elseif (is_object($param)) {
				$param = get_class($param);
			}

			// Use custom label instead of field name if set
			if (is_string($params[0]) && isset($this->_labels[$param])) {
				$param = $this->_labels[$param];
			}
			$values[] = $param;
		}

		$this->_errors[$field][] = vsprintf($msg, $values);
	}

	/**
	 * Specify validation message to use for error for the last validation rule
	 *
	 * @param  string $msg
	 *
	 * @return $this
	 */
	public function message(string $msg)
	{
		$this->_validations[count($this->_validations) - 1]['message'] = $msg;

		return $this;
	}

	/**
	 * Reset object properties
	 *
	 * @return $this
	 */
	public function reset()
	{
		$this->_fields = [];
		$this->_errors = [];
		$this->_validations = [];
		$this->_labels = [];

		return $this;
	}

	protected function getPart($data, $identifiers)
	{
		// Catches the case where the field is an array of discrete values
		if (is_array($identifiers) && count($identifiers) === 0) {
			return [$data, false];
		}

		$identifier = array_shift($identifiers);

		// Glob match
		if ($identifier === '*') {
			$values = [
				0 => [],
			];
			foreach ($data as $row) {
				list($value, $multiple) = $this->getPart($row, $identifiers);
				if ($multiple) {
					$values[] = $value;
				} else {
					$values[0][] = $value;
				}
			}

			return [
				call_user_func_array('array_merge', $values),
				true,
			];
		} // Dead end, abort
		elseif ($identifier === null || !isset($data[$identifier])) {
			return [null, false];
		} // Match array element
		elseif (count($identifiers) === 0) {
			return [$data[$identifier], false];
		} // We need to go deeper
		else {
			return $this->getPart($data[$identifier], $identifiers);
		}
	}

	/**
	 * Run validations and return boolean result
	 *
	 * @return boolean
	 */
	public function validate()
	{
		foreach ($this->_validations as $v) {
			foreach ($v['fields'] as $field) {
				list($values, $multiple) = $this->getPart($this->_fields, explode('.', $field));

				// Don't validate if the field is not required and the value is empty
				if ($this->hasRule('optional', $field) && isset($values)) {
					//Continue with execution below if statement
				} elseif ($v['rule'] !== 'required' && !$this->hasRule('required', $field) && (!isset($values) || $values === '' || ($multiple && count($values) == 0))) {
					continue;
				}

				// Callback is user-specified or assumed method on class
				$errors = $this->getRules();
				if (isset($errors[$v['rule']])) {
					$callback = $errors[$v['rule']];
				} else {
					$callback = [$this, 'validate' . ucfirst($v['rule'])];
				}

				if (!$multiple) {
					$values = [$values];
				}

				$result = true;
				foreach ($values as $value) {
					$result = $result && call_user_func($callback, $field, $value, $v['params'], $this->_fields);
				}

				if (!$result) {
					$this->error($field, $v['message'], $v['params']);
				}
			}
		}

		return count($this->errors()) === 0;
	}

	/**
	 * Returns all rule callbacks, the static and instance ones.
	 *
	 * @return array
	 */
	protected function getRules()
	{
		return array_merge($this->_instanceRules, static::$_rules);
	}

	/**
	 * Returns all rule message, the static and instance ones.
	 *
	 * @return array
	 */
	protected function getRuleMessages()
	{
		return array_merge($this->_instanceRuleMessage, static::$_ruleMessages);
	}

	/**
	 * Determine whether a field is being validated by the given rule.
	 *
	 * @param  string $name  The name of the rule
	 * @param  string $field The name of the field
	 *
	 * @return boolean
	 */

	protected function hasRule($name, $field)
	{
		foreach ($this->_validations as $validation) {
			if ($validation['rule'] === $name) {
				if (in_array($field, $validation['fields'], true)) {
					return true;
				}
			}
		}

		return false;
	}

	protected static function assertRuleCallback($callback)
	{
		if (!is_callable($callback)) {
			throw new InvalidArgumentException('Second argument must be a valid callback. Given argument was not callable.');
		}
	}

	/**
	 * Adds a new validation rule callback that is tied to the current
	 * instance only.
	 *
	 * @param string $name
	 * @param mixed  $callback
	 * @param string $message
	 *
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function addInstanceRule(string $name, callable $callback, string $message = null)
	{
		static::assertRuleCallback($callback);

		$this->_instanceRules[$name] = $callback;
		$this->_instanceRuleMessage[$name] = $message;

		return $this;
	}

	/**
	 * Register new validation rule callback
	 *
	 * @param  string $name
	 * @param  mixed  $callback
	 * @param  string $message
	 *
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function addRule(string $name, callable $callback, string $message = null)
	{
		if ($message === null) {
			$message = static::ERROR_DEFAULT;
		}

		static::assertRuleCallback($callback);

		static::$_rules[$name] = $callback;
		static::$_ruleMessages[$name] = $message;

		return $this;
	}

	/**
	 * @param string|array $fields
	 *
	 * @return string
	 */
	public function getUniqueRuleName($fields): string
	{
		if (is_array($fields)) {
			$fields = implode('_', $fields);
		}

		$orgName = "{$fields}_rule";
		$name = $orgName;
		$rules = $this->getRules();
		while (isset($rules[$name])) {
			$name = $orgName . '_' . random_int(0, 10000);
		}

		return $name;
	}

	/**
	 * Returns true if either a valdiator with the given name has been
	 * registered or there is a default validator by that name.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasValidator(string $name): bool
	{
		$rules = $this->getRules();

		return method_exists($this, 'validate' . ucfirst($name)) || isset($rules[$name]);
	}

	/**
	 * Convenience method to add a single validation rule
	 *
	 * @param  string|callback $rule
	 * @param  array|string    $fields
	 * @param  mixed           ...$params
	 *
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function rule($rule, $fields, ...$params)
	{
		if (is_callable($rule)
			&& !(is_string($rule) && $this->hasValidator($rule))
		) {
			$name = $this->getUniqueRuleName($fields);
			$msg = $params[0] ?? null;
			$this->addInstanceRule($name, $rule, $msg);
			$rule = $name;
		}

		$errors = $this->getRules();
		if (!isset($errors[$rule])) {
			$ruleMethod = 'validate' . ucfirst($rule);
			if (!method_exists($this, $ruleMethod)) {
				throw new InvalidArgumentException("Rule '" . $rule . "' has not been registered with " . __CLASS__ . "::addRule().");
			}
		}

		// Ensure rule has an accompanying message
		$msgs = $this->getRuleMessages();
		$message = $msgs[$rule] ?? self::ERROR_DEFAULT;

		$this->_validations[] = [
			'rule' => $rule,
			'fields' => (array) $fields,
			'params' => (array) $params,
			'message' => '{field} ' . $message,
		];

		return $this;
	}

	/**
	 * @param  string $value
	 *
	 * @internal param array $labels
	 * @return $this
	 */
	public function label($value)
	{
		$lastRules = $this->_validations[count($this->_validations) - 1]['fields'];
		$this->labels([$lastRules[0] => $value]);

		return $this;
	}

	/**
	 * @param  array $labels
	 *
	 * @return $this
	 */
	public function labels(array $labels = [])
	{
		$this->_labels = array_merge($this->_labels, $labels);

		return $this;
	}

	/**
	 * @param  string $field
	 * @param  string $msg
	 * @param  array  $params
	 *
	 * @return string
	 */
	protected function checkAndSetLabel($field, $msg, $params)
	{
		if (isset($this->_labels[$field])) {
			$msg = str_replace('{field}', $this->_labels[$field], $msg);

			if (is_array($params)) {
				$i = 1;
				foreach ($params as $k => $v) {
					$tag = '{field' . $i . '}';
					$label = isset($params[$k]) &&
					(is_numeric($params[$k]) || is_string($params[$k])) &&
					isset($this->_labels[$params[$k]]) ?
						$this->_labels[$params[$k]] : $tag;
					$msg = str_replace($tag, $label, $msg);
					$i++;
				}
			}
		} else {
			$msg = str_replace('{field}', ucwords(str_replace('_', ' ', $field)), $msg);
		}

		return $msg;
	}

	/**
	 * Convenience method to add multiple validation rules with an array
	 *
	 * @param array $rules
	 *
	 * @return $this
	 */
	public function rules(array $rules)
	{
		foreach ($rules as $ruleType => $params) {
			if (is_array($params)) {
				foreach ($params as $innerParams) {
					array_unshift($innerParams, $ruleType);
					call_user_func_array([$this, 'rule'], $innerParams);
				}
			} else {
				$this->rule($ruleType, $params);
			}
		}

		return $this;
	}

	/**
	 * Replace data on cloned instance
	 *
	 * @param  array $data
	 * @param  array $fields
	 *
	 * @return Validator
	 */
	public function withData(array $data, array $fields = [])
	{
		$clone = clone $this;
		$clone->_fields = !empty($fields) ? array_intersect_key($data, array_flip($fields)) : $data;
		$clone->_errors = [];

		return $clone;
	}
}