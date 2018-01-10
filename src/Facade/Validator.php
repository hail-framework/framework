<?php
namespace Hail\Facade;

/**
 * Class Validator
 *
 * @package Hail\Facade
 *
 * @method static \Hail\Util\Validator withData(array $data, array $fields = [])
 * @method static \Hail\Util\Validator data(array $data, array $fields = [])
 * @method static array getData()
 * @method static array|bool errors(string $field = null)
 * @method static void error(string $field, string $msg, array $params = [])
 * @method static \Hail\Util\Validator message(string $msg)
 * @method static \Hail\Util\Validator reset()
 * @method static bool validate()
 * @method static \Hail\Util\Validator addInstanceRule(string $name, callable $callback, string $message = null)
 * @method static \Hail\Util\Validator addRule(string $name, callable $callback, string $message = null)
 * @method static string|array getUniqueRuleName(string $fields)
 * @method static bool hasValidator(string $name)
 * @method static \Hail\Util\Validator rule(string|callback $rule, array $fields, ...$params)
 * @method static \Hail\Util\Validator label(string|array $value)
 * @method static \Hail\Util\Validator labels(array $labels = [])
 * @method static \Hail\Util\Validator rules(array $rules)
 */
class Validator extends Facade
{
}