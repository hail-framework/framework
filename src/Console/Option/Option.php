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

namespace Hail\Console\Option;

use InvalidArgumentException;
use Hail\Console\ValueType;
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

    /**
     * @var string[]
     */
    public $validValues;

    /**
     * @var string[]
     */
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

    public function __construct(string $spec, string $desc = null)
    {
        $this->initFromSpec($spec);

        if ($desc) {
            $this->desc($desc);
        }
    }

    /**
     * Build spec attributes from spec string.
     *
     * @param string $specString
     * @throws InvalidArgumentException
     */
    protected function initFromSpec(string $specString)
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
        (?:=(bool|boolean|string|int|number|date|datetime|file|dir|url|email|ip|ipv6|ipv4))?
        /x';
        $ret = preg_match($pattern, $specString, $regs);
        if ($ret === false || $ret === 0) {
            throw new InvalidArgumentException('Incorrect spec string');
        }

        // $orig = $regs[0];
        $name = $regs[1];
        $attributes = $regs[2] ?? null;
        $type = $regs[3] ?? null;

        $short = null;
        $long = null;

        // check long,short option name.
        if (strpos($name, '|') !== false) {
            list($short, $long) = explode('|', $name);
        } elseif (strlen($name) === 1) {
            $short = $name;
        } elseif (strlen($name) > 1) {
            $long = $name;
        }

        $this->short = $short;
        $this->long = $long;

        // option is required.
        if (strpos($attributes, ':') !== false) {
            $this->required();
        } elseif (strpos($attributes, '+') !== false) {
            // option with multiple value
            $this->multiple();
        } elseif (strpos($attributes, '?') !== false) {
            // option is optional.(zero or one value)
            $this->optional();
        } else {
            $this->flag();
        }
        if ($type) {
            $this->isa($type);
        }
    }

    /*
     * get the option key for result key mapping.
     */
    public function getId()
    {
        return $this->key ?: $this->long ?: $this->short;
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

    /**
     * Set default value
     *
     * @param mixed|\Closure $value
     *
     * @return self
     */
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

    public function trigger(callable $trigger)
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
        return $this->isa === 'number' || $this->isa === 'int';
    }

    public function isType($type)
    {
        return $this->isa === $type;
    }

    public function validate($value)
    {
        $val = $value;

        if ($isa = ucfirst($this->isa)) {
            if (ValueType::test($isa, $value, $this->isaOption)) {
                $val = ValueType::parse();
            } else {
                if ($this->isaOption) {
                    $isa .= '(' . $this->isaOption . ')';
                }
                throw new InvalidOptionValueException("Invalid value for {$this->renderReadableSpec(false)}. Requires a type $isa.");
            }
        }

        // check pre-filter for option value
        if ($this->filter) {
            $val = ($this->filter)($val);
        }

        // check validValues
        if (
            ($validValues = $this->getValidValues()) &&
            !in_array($value, $validValues, true)
        ) {
            throw new InvalidOptionValueException('valid values are: ' . implode(', ', $validValues));
        }

        if ($this->validator && !($this->validator)($value)) {
            throw new InvalidOptionValueException('option is invalid');
        }

        return $val;
    }

    protected function callTrigger()
    {
        if ($this->trigger && $ret = ($this->trigger)($this->value)) {
            $this->value = $ret;
        }
    }

    /*
     * set option value
     */
    public function setValue($value)
    {
        $this->value = $this->validate($value);
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
        $this->value[] = $this->validate($value);
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
        } elseif ($values = $this->getValidValues()) {
            $n = '(' . implode(',', $values) . ')';
        } elseif ($values = $this->getSuggestions()) {
            $n = '[' . implode(',', $values) . ']';
        } elseif ($val = $this->getDefaultValue()) {
            // This allows for `0` and `false` values to be displayed also.
            if (is_bool($val)) {
                $n = ($val ? 'true' : 'false');
            } elseif (is_scalar($val) && strlen((string) $val)) {
                $n = $val;
            }
        }

        if (!$n && $this->isa !== null) {
            $n = '<' . $this->isa . '>';
        }

        if ($this->isRequired()) {
            return '=' . $n;
        }

        if ($this->defaultValue || $this->isOptional()) {
            return "[=$n]";
        }

        if ($n) {
            return '=' . $n;
        }

        return '';
    }

    public function getDefaultValue()
    {
        if (is_callable($this->defaultValue)) {
            return $this->defaultValue;
        }

        return $this->defaultValue;
    }

    public function getValue()
    {
        if (null !== $this->value) {
            return $this->value;
        }

        return $this->getDefaultValue();
    }

    /**
     * get readable spec for printing.
     *
     * @param bool $renderHint render also value hint
     *
     * @return string
     */
    public function renderReadableSpec($renderHint = true)
    {
        $c1 = '';
        if ($this->short) {
            $c1 = '-' . $this->short;
        }

        if ($this->long) {
            if ($c1 !== '') {
                $c1 .= ', ';
            }

            $c1 .= '--' . $this->long;
        }

        if ($renderHint) {
            return $c1 . $this->renderValueHint();
        }

        return $c1;
    }

    public function __toString()
    {
        $c1 = $this->renderReadableSpec();
        $return = '';
        $return .= sprintf('* key:%-8s spec:%s  desc:%s', $this->getId(), $c1, $this->desc) . "\n";
        $val = $this->getValue();
        if (is_array($val)) {
            foreach ($val as &$v) {
                $v = var_export($v, true);
            }

            $return .= '  value => ' . implode(',', $val) . "\n";
        } else {
            $return .= sprintf('  value => %s', $val) . "\n";
        }

        return $return;
    }

    /**
     * Value Type Setters.
     *
     * @param string $type   the value type, valid values are 'number', 'string',
     *                       'file', 'boolean', you can also use your own value type name.
     * @param string $option option(s) for value type class (optionnal)
     *
     * @return self
     */
    public function isa($type, $option = null)
    {
        // "bool" was kept for backward compatibility
        if ($type === 'bool') {
            $type = 'boolean';
        }
        $this->isa = $type;
        $this->isaOption = $option;

        return $this;
    }

    /**
     * Assign validValues to member value.
     *
     * @param array $values
     *
     * @return self
     */
    public function validValues(array $values)
    {
        $this->validValues = $values;

        return $this;
    }

    /**
     * Assign suggestions.
     *
     * @param array $suggestions
     *
     * @return self
     */
    public function suggestions(array $suggestions)
    {
        $this->suggestions = $suggestions;

        return $this;
    }

    /**
     * Return valud values array.
     *
     * @return string[]
     */
    public function getValidValues(): array
    {
        return $this->validValues ?? [];
    }

    /**
     * Return suggestions.
     *
     * @return string[]
     */
    public function getSuggestions(): array
    {
        return $this->suggestions ?? [];
    }


    public function validator(callable $cb)
    {
        $this->validator = $cb;

        return $this;
    }

    /**
     * Set up a filter function for the option value.
     *
     * @param callable $cb
     *
     * @return self
     */
    public function filter(callable $cb)
    {
        $this->filter = $cb;

        return $this;
    }
}
