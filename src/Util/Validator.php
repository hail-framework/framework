<?php
/**
 * https://github.com/vlucas/valitron/
 *
 * @copyright  Vance Lucas <vance@vancelucas.com>
 * @link       http://www.vancelucas.com/
 */

namespace Hail\Util;


/**
 * Validates input against certain criteria
 */
class Validator
{
    /**
     * @var string
     */
    protected const ERROR_DEFAULT = 'Invalid';

    public const SKIP_CONTINUE = 0;
    public const SKIP_ONE = 1;
    public const SKIP_ALL = 2;

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
    protected $_skips = [];

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
     * @var bool
     */
    protected $stopOnFirstFail = false;

    public function __construct()
    {
        static::$_ruleMessages = [
            'required' => _('不能为空'),
            'equals' => _('必须和 "%s" 一致'),
            'different' => _('必须和 "%s" 不一致'),
            'accepted' => _('必须接受'),
            'numeric' => _('只能是数字'),
            'integer' => _('只能是整数'),
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
            'boolean' => _('必须是真或假'),
            'lengthBetween' => _('长度只能介于 %d 和 %d 之间'),
            'creditCard' => _('信用卡号码不正确'),
            'lengthMin' => _('长度必须大于 %d'),
            'lengthMax' => _('长度必须小于 %d'),
        ];
    }

    /**
     * Required field validator
     *
     * @param string $field
     * @param mixed  $value
     * @param array  $params
     * @param array  $fields
     *
     * @return bool
     */
    protected function validateRequired($field, $value, array $params = [], array $fields = [])
    {
        if (isset($params[0]) && (bool) $params[0]) {
            $find = $this->getPart($fields, \explode('.', $field), true);

            return $find[1];
        }

        if (null === $value) {
            return false;
        }

        if (\is_string($value) && \trim($value) === '') {
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
        // extract the second field value, this accounts for nested array values
        [$field2Value, $multiple] = $this->getPart($this->_fields, \explode('.', $params[0]));

        return null !== $field2Value && $value === $field2Value;
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
        // extract the second field value, this accounts for nested array values
        [$field2Value, $multiple] = $this->getPart($this->_fields, \explode('.', $params[0]));

        return null !== $field2Value && $value !== $field2Value;
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

        return $this->validateRequired($field, $value) && \in_array($value, $acceptable, true);
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
        return \is_array($value);
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
        return \is_numeric($value);
    }

    /**
     * Validate that a field is an integer
     *
     * @param  string $field
     * @param  mixed  $value
     *
     * @return bool
     */
    protected function validateInteger($field, $value, $params)
    {
        if (isset($params[0]) && (bool) $params[0]) {
            //strict mode
            return preg_match('/^(\d|-[1-9]|-?[1-9]\d*)$/i', $value);
        }

        return \filter_var($value, \FILTER_VALIDATE_INT) !== false;
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
     * @return bool
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
     * @return bool
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
     * @return bool
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
        if (!\is_string($value)) {
            return false;
        }

        return \mb_strlen($value);
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
        if (!\is_numeric($value)) {
            return false;
        }

        if (\function_exists('\bccomp')) {
            return !(\bccomp($params[0], $value, 14) === 1);
        }

        return $params[0] <= $value;
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
        if (!\is_numeric($value)) {
            return false;
        }

        if (\function_exists('\bccomp')) {
            return !(\bccomp($value, $params[0], 14) === 1);
        }

        return $params[0] >= $value;
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
        if (!\is_numeric($value)) {
            return false;
        }
        if (!isset($params[0]) || !\is_array($params[0]) || \count($params[0]) !== 2) {
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
        $isAssoc = \array_values($params[0]) !== $params[0];
        if ($isAssoc) {
            $params[0] = \array_keys($params[0]);
        }

        $strict = false;
        if (isset($params[1])) {
            $strict = $params[1];
        }

        return \in_array($value, $params[0], $strict);
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
     * @param  string $value
     * @param  array  $params
     *
     * @return bool
     */
    protected function validateContains($field, $value, $params)
    {
        if (!isset($params[0])) {
            return false;
        }
        if (!\is_string($value) || !\is_string($params[0])) {
            return false;
        }

        $strict = true;
        if (isset($params[1])) {
            $strict = (bool) $params[1];
        }

        if ($strict) {
            return \mb_strpos($value, $params[0]) !== false;
        }

        return \mb_stripos($value, $params[0]) !== false;
    }

    /**
     * Validate that all field values contains a given array
     *
     * @param  string $field
     * @param  array  $value
     * @param  array  $params
     *
     * @return bool
     */
    protected function validateSubset($field, $value, $params)
    {
        if (!isset($params[0])) {
            return false;
        }
        if (!\is_array($params[0])) {
            $params[0] = [$params[0]];
        }
        if (\is_scalar($value)) {
            return $this->validateIn($field, $value, $params);
        }
        $intersect = \array_intersect($value, $params[0]);

        return \array_diff($value, $intersect) === \array_diff($intersect, $value);
    }

    /**
     * Validate that field array has only unique values
     *
     * @param  string $field
     * @param  array  $value
     *
     * @return bool
     */
    protected function validateContainsUnique($field, $value)
    {
        if (!\is_array($value)) {
            return false;
        }

        return $value === \array_unique($value, SORT_REGULAR);
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
        return \filter_var($value, \FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate that a field is a valid IP v4 address
     *
     * @param  string $field
     * @param  mixed  $value
     *
     * @return bool
     */
    protected function validateIpv4($field, $value)
    {
        return \filter_var($value, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Validate that a field is a valid IP v6 address
     *
     * @param  string $field
     * @param  mixed  $value
     *
     * @return bool
     */
    protected function validateIpv6($field, $value)
    {
        return \filter_var($value, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) !== false;
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
        return \filter_var($value, \FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate that a field contains only ASCII characters
     *
     * @param $field
     * @param $value
     *
     * @return bool|false|string
     */
    protected function validateAscii($field, $value)
    {
        // multibyte extension needed
        if (\function_exists('\\mb_detect_encoding')) {
            return \mb_detect_encoding($value, 'ASCII', true);
        }

        // fallback with regex
        return 0 === \preg_match('/[^\x00-\x7F]/', $value);
    }

    /**
     * Validate that a field is a valid e-mail address and the domain name is active
     *
     * @param  string $field
     * @param  mixed  $value
     *
     * @return bool
     */
    protected function validateEmailDNS($field, $value)
    {
        if ($this->validateEmail($field, $value)) {
            $domain = \ltrim(\strstr($value, '@'), '@') . '.';
            if (\defined('INTL_IDNA_VARIANT_UTS46') && \function_exists('\idn_to_ascii')) {
                $domain = \idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
            }

            return \checkdnsrr($domain, 'ANY');
        }

        return false;
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
            if (\strpos($value, $prefix) !== false) {
                return \filter_var($value, \FILTER_VALIDATE_URL) !== false;
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
            if (\strpos($value, $prefix) !== false) {
                $host = \parse_url(\strtolower($value), PHP_URL_HOST);

                return \checkdnsrr($host, 'A') || \checkdnsrr($host, 'AAAA') || \checkdnsrr($host, 'CNAME');
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
        return \preg_match('/^([a-z])+$/i', $value);
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
        return \preg_match('/^([a-z0-9])+$/i', $value);
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
        if (\is_array($value)) {
            return false;
        }

        return \preg_match('/^([-a-z0-9_-])+$/i', $value);
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
        return \preg_match($params[0], $value);
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

        return \strtotime($value) !== false;
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
        $parsed = \date_parse_from_format($params[0], $value);

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
        $vtime = ($value instanceof \DateTime) ? $value->getTimestamp() : \strtotime($value);
        $ptime = ($params[0] instanceof \DateTime) ? $params[0]->getTimestamp() : \strtotime($params[0]);

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
        $vtime = ($value instanceof \DateTime) ? $value->getTimestamp() : \strtotime($value);
        $ptime = ($params[0] instanceof \DateTime) ? $params[0]->getTimestamp() : \strtotime($params[0]);

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
        return \is_bool($value);
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
            if (\is_array($params[0])) {
                $cards = $params[0];
            } elseif (\is_string($params[0])) {
                $cardType = $params[0];
                if (isset($params[1]) && \is_array($params[1])) {
                    $cards = $params[1];
                    if (!\in_array($cardType, $cards, true)) {
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
                return (\preg_match($cardRegex[$cardType], $value) === 1);
            }

            if (isset($cards)) {
                // if we have cards, check our users card against only the ones we have
                foreach ($cards as $card) {
                    if (
                        isset($cardRegex[$card]) &&
                        \preg_match($cardRegex[$card], $value) === 1 // if the card is valid, we want to stop looping
                    ) {
                        return true;
                    }
                }
            } else {
                // loop through every card
                foreach ($cardRegex as $regex) {
                    // until we find a valid one
                    if (\preg_match($regex, $value) === 1) {
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
        $number = \preg_replace('/\D+/', '', $value);
        $sum = 0;

        $len = \strlen($number);
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
        if (\is_object($value)) {
            if (\is_object($params[0]) && $value instanceof $params[0]) {
                return true;
            }

            if (\get_class($value) === $params[0]) {
                return true;
            }
        }

        return \is_string($value) && \is_string($params[0]) && \get_class($value) === $params[0];
    }

    /**
     * Validate optional field
     *
     * @param $field
     * @param $value
     * @param $params
     *
     * @return bool
     */
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
        $this->_fields = $fields !== [] ? \array_intersect_key($data, \array_flip($fields)) : $data;

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
     * @param string $message
     * @param array  $params
     */
    public function error(string $field, string $message, array $params = [])
    {
        $message = $this->checkAndSetLabel($field, $message, $params);

        $values = [];
        // Printed values need to be in string format
        foreach ($params as $param) {
            if (\is_array($param)) {
                $param = "['" . implode("', '", $param) . "']";
            } elseif ($param instanceof \DateTime) {
                $param = $param->format('Y-m-d');
            } elseif (\is_object($param)) {
                $param = \get_class($param);
            }

            // Use custom label instead of field name if set
            if (\is_string($params[0]) && isset($this->_labels[$param])) {
                $param = $this->_labels[$param];
            }
            $values[] = $param;
        }

        $this->_errors[$field][] = \vsprintf($message, $values);
    }

    /**
     * Specify validation message to use for error for the last validation rule
     *
     * @param  string $message
     *
     * @return $this
     */
    public function message(string $message)
    {
        $this->_validations[\count($this->_validations) - 1]['message'] = $message;

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
        $this->_skips = [];

        return $this;
    }

    protected function getPart($data, array $identifiers, $allowEmpty = false)
    {
        // Catches the case where the field is an array of discrete values
        if ($identifiers === []) {
            return [$data, false];
        }

        // Catches the case where the data isn't an array or object
        if (\is_scalar($data)) {
            return [null, false];
        }

        $identifier = \array_shift($identifiers);

        // Glob match
        if ($identifier === '*') {
            $values = [];
            foreach ($data as $row) {
                [$value, $multiple] = $this->getPart($row, $identifiers, $allowEmpty);
                if ($multiple) {
                    $values[] = $value;
                } else {
                    $values[] = [$value];
                }
            }

            return [
                \array_merge(...$values),
                true,
            ];
        }

        // Dead end, abort
        if ($identifier === null || !isset($data[$identifier])) {
            if ($allowEmpty) {
                //when empty values are allowed, we only care if the key exists
                return [null, \array_key_exists($identifier, $data)];
            }

            return [null, false];
        }

        // Match array element
        if ($identifiers === []) {
            if ($allowEmpty) {
                //when empty values are allowed, we only care if the key exists
                return [null, \array_key_exists($identifier, $data)];
            }

            return [$data[$identifier], false];
        }

        // We need to go deeper
        return $this->getPart($data[$identifier], $identifiers, $allowEmpty);
    }

    /**
     * Run validations and return boolean result
     *
     * @return bool
     */
    public function validate()
    {
        $setToBreak = false;

        foreach ($this->_validations as $v) {
            foreach ($v['fields'] as $field) {
                if (isset($this->_skips[$field])) {
                    if ($this->_skips[$field] === self::SKIP_ALL) {
                        break 2;
                    }

                    if ($this->_skips[$field] === self::SKIP_ONE) {
                        break;
                    }
                }

                [$values, $multiple] = $this->getPart($this->_fields, \explode('.', $field));

                // Don't validate if the field is not required and the value is empty
                if (null !== $values && $this->hasRule('optional', $field)) {
                    //Continue with execution below if statement
                } elseif (
                    $v['rule'] !== 'accepted' &&
                    $v['rule'] !== 'required' && !$this->hasRule('required', $field) &&
                    (null === $values || $values === '' || ($multiple && \count($values) === 0))
                ) {
                    continue;
                }

                // Callback is user-specified or assumed method on class
                $errors = $this->getRules();
                if (isset($errors[$v['rule']])) {
                    $callback = $errors[$v['rule']];
                } else {
                    $callback = [$this, 'validate' . \ucfirst($v['rule'])];
                }

                if (!$multiple) {
                    $values = [$values];
                }

                $result = true;
                foreach ($values as $value) {
                    $result = $result && $callback($field, $value, $v['params'], $this->_fields);
                }

                if (!$result) {
                    $this->error($field, $v['message'], $v['params']);
                    if ($this->stopOnFirstFail) {
                        $setToBreak = true;
                        break;
                    }
                    $this->_skips[$field] = $v['skip'];
                }
            }

            if ($setToBreak) {
                break;
            }
        }

        return \count($this->errors()) === 0;
    }

    /**
     * Should the validation stop a rule is failed
     *
     * @param bool $stop
     *
     * @return static
     */
    public function stopOnFirstFail(bool $stop): self
    {
        $this->stopOnFirstFail = $stop;

        return $this;
    }

    /**
     * If the validation for a field fails, skip all other checks for this field.
     *
     * @return static
     */
    public function onErrorSkipField(): self
    {
        $this->_validations[\count($this->_validations) - 1]['skip'] = self::SKIP_ONE;

        return $this;
    }

    /**
     * If the validation of a field fails, stop the validation process.
     *
     * @return static
     */
    public function onErrorQuit(): self
    {
        $this->_validations[\count($this->_validations) - 1]['skip'] = self::SKIP_ALL;

        return $this;
    }

    /**
     * Returns all rule callbacks, the static and instance ones.
     *
     * @return array
     */
    protected function getRules(): array
    {
        return \array_merge($this->_instanceRules, static::$_rules);
    }

    /**
     * Returns all rule message, the static and instance ones.
     *
     * @return array
     */
    protected function getRuleMessages(): array
    {
        return \array_merge($this->_instanceRuleMessage, static::$_ruleMessages);
    }

    /**
     * Determine whether a field is being validated by the given rule.
     *
     * @param  string $name  The name of the rule
     * @param  string $field The name of the field
     *
     * @return bool
     */
    protected function hasRule(string $name, string $field): bool
    {
        foreach ($this->_validations as $validation) {
            if ($validation['rule'] === $name && \in_array($field, $validation['fields'], true)) {
                return true;
            }
        }

        return false;
    }

    protected static function assertRuleCallback($callback): void
    {
        if (!\is_callable($callback)) {
            throw new \InvalidArgumentException('Second argument must be a valid callback. Given argument was not callable.');
        }
    }

    /**
     * Adds a new validation rule callback that is tied to the current
     * instance only.
     *
     * @param string   $name
     * @param callable $callback
     * @param string   $message
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addInstanceRule(string $name, callable $callback, string $message = null): self
    {
        static::assertRuleCallback($callback);

        $this->_instanceRules[$name] = $callback;
        $this->_instanceRuleMessage[$name] = $message;

        return $this;
    }

    /**
     * Register new validation rule callback
     *
     * @param  string   $name
     * @param  callable $callback
     * @param  string   $message
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addRule(string $name, callable $callback, string $message = null): self
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
        if (\is_array($fields)) {
            $fields = \implode('_', $fields);
        }

        $orgName = "{$fields}_rule";
        $name = $orgName;
        $rules = $this->getRules();
        while (isset($rules[$name])) {
            $name = $orgName . '_' . \random_int(0, 10000);
        }

        return $name;
    }

    /**
     * Returns true if either a validator with the given name has been
     * registered or there is a default validator by that name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasValidator(string $name): bool
    {
        $rules = $this->getRules();

        return \method_exists($this, 'validate' . \ucfirst($name)) || isset($rules[$name]);
    }

    /**
     * Convenience method to add a single validation rule
     *
     * @param  string|callable $rule
     * @param  array|string    $fields
     * @param  mixed           ...$params
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function rule($rule, $fields, ...$params)
    {
        if (\is_callable($rule)
            && !(\is_string($rule) && $this->hasValidator($rule))
        ) {
            $name = $this->getUniqueRuleName($fields);
            $message = $params[0] ?? null;
            $this->addInstanceRule($name, $rule, $message);
            $rule = $name;
        }

        $errors = $this->getRules();
        if (!isset($errors[$rule])) {
            $ruleMethod = 'validate' . \ucfirst($rule);
            if (!\method_exists($this, $ruleMethod)) {
                throw new \InvalidArgumentException("Rule '" . $rule . "' has not been registered with " . __CLASS__ . "::addRule().");
            }
        }

        // Ensure rule has an accompanying message
        $messages = $this->getRuleMessages();
        $message = $messages[$rule] ?? self::ERROR_DEFAULT;

        // Ensure message contains field label
        if (\strpos($message, '{field}') === false) {
            $message = '{field} ' . $message;
        }

        $this->_validations[] = [
            'rule' => $rule,
            'fields' => (array) $fields,
            'params' => (array) $params,
            'message' => '{field} ' . $message,
            'skip' => self::SKIP_CONTINUE,
        ];

        return $this;
    }

    /**
     * Add label to rule
     *
     * @param  string $value
     *
     * @internal param array $labels
     * @return $this
     */
    public function label($value)
    {
        $lastRules = $this->_validations[\count($this->_validations) - 1]['fields'];
        $this->labels([$lastRules[0] => $value]);

        return $this;
    }

    /**
     * Add labels to rules
     *
     * @param  array $labels
     *
     * @return $this
     */
    public function labels(array $labels = [])
    {
        $this->_labels = \array_merge($this->_labels, $labels);

        return $this;
    }

    /**
     * @param  string $field
     * @param  string $message
     * @param  array  $params
     *
     * @return string
     */
    protected function checkAndSetLabel($field, $message, $params)
    {
        if (isset($this->_labels[$field])) {
            $message = \str_replace('{field}', $this->_labels[$field], $message);

            if (\is_array($params)) {
                $i = 1;
                foreach ($params as $k => $v) {
                    $tag = '{field' . $i . '}';
                    $label = isset($params[$k]) &&
                    (\is_numeric($params[$k]) || \is_string($params[$k])) &&
                    isset($this->_labels[$params[$k]]) ?
                        $this->_labels[$params[$k]] : $tag;
                    $message = \str_replace($tag, $label, $message);
                    $i++;
                }
            }
        } else {
            $message = \str_replace('{field}', \ucwords(\str_replace('_', ' ', $field)), $message);
        }

        return $message;
    }

    /**
     * Convenience method to add multiple validation rules with an array
     *
     * @param array $rules
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function rules(array $rules)
    {
        foreach ($rules as $ruleType => $params) {
            if (\is_array($params)) {
                foreach ($params as $innerParams) {
                    $innerParams = (array) $innerParams;
                    $this->rule($ruleType, ...$innerParams);
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
     * @return \Hail\Util\Validator
     */
    public function withData(array $data, array $fields = [])
    {
        $clone = clone $this;
        $clone->_fields = !empty($fields) ? \array_intersect_key($data, \array_flip($fields)) : $data;
        $clone->_errors = [];

        return $clone;
    }

    /**
     * Convenience method to add validation rule(s) by field
     *
     * @param string $field
     * @param array  $rules
     *
     * @throws \InvalidArgumentException
     */
    public function mapFieldRules(string $field, array $rules)
    {
        foreach ($rules as $rule) {
            //rule must be an array
            $rule = (array) $rule;

            //First element is the name of the rule
            $name = \array_shift($rule);

            //find a custom message, if any
            $message = null;
            if (isset($rule['message'])) {
                $message = $rule['message'];
                unset($rule['message']);
            }

            //Add the field and additional parameters to the rule
            $this->rule($name, $field, ...$rule);

            if (!empty($message)) {
                $this->message($message);
            }
        }
    }

    /**
     * Convenience method to add validation rule(s) for multiple fields
     *
     * @param array $rules
     *
     * @throws \InvalidArgumentException
     */
    public function mapFieldsRules(array $rules)
    {
        foreach ($rules as $field => $rule) {
            $this->mapFieldRules($field, $rule);
        }
    }
}