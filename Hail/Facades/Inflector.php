<?php
namespace Hail\Facades;

/**
 * Class Inflector
 *
 * @package Hail\Facades
 *
 * @method static void reset()
 * @method static void rules(string $type, array $rules, bool $reset = false)
 * @method static string pluralize(string $word)
 * @method static string singularize(string $word)
 * @method static string camelize(string $string, string $delimiter = '_')
 * @method static string underscore(string $string)
 * @method static string dasherize(string $string)
 * @method static string humanize(string $string, string $delimiter = '_')
 * @method static string delimit(string $string, string $delimiter = '_')
 * @method static string tableize(string $className)
 * @method static string classify(string $tableName)
 * @method static string variable(string $string)
 * @method static string slug(string $string, string $replacement = '-')
 */
class Inflector extends Facade
{
	protected static function instance()
	{
		return \Hail\Util\Inflector::getInstance();
	}
}