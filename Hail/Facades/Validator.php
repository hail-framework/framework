<?php
namespace Hail\Facades;

/**
 * Class Validator
 *
 * @package Hail\Facades
 *
 * @method static \Hail\Utils\Validator withData(array $data, array $fields = [])
 * @method static \Hail\Utils\Validator data(array $data, array $fields = [])
 * @method static array getData()
 * @method static array|bool errors(string $field = null)
 * @method static void error(string $field, string $msg, array $params = [])
 * @method static \Hail\Utils\Validator message(string $msg)
 * @method static \Hail\Utils\Validator reset()
 * @method static bool validate()
 * @method static \Hail\Utils\Validator addInstanceRule(string $name, callable $callback, string $message = null)
 * @method static \Hail\Utils\Validator addRule(string $name, callable $callback, string $message = null)
 * @method static string|array getUniqueRuleName(string $fields)
 * @method static bool hasValidator(string $name)
 * @method static \Hail\Utils\Validator rule(string|callback $rule, array $fields, ...$params)
 * @method static \Hail\Utils\Validator label(string|array $value)
 * @method static \Hail\Utils\Validator labels(array $labels = [])
 * @method static \Hail\Utils\Validator rules(array $rules)
 */
class Validator extends Facade
{
	protected static function instance()
	{
		return \Hail\Utils\Validator::getInstance();
	}
}