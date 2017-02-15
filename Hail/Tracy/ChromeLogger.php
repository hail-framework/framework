<?php
namespace Hail\Tracy;

use Hail\Util\SingletonTrait;

/**
 * ChromeLogger console logger.
 *
 * @see https://craig.is/writing/chrome-logger
 * @see https://developer.mozilla.org/en-US/docs/Tools/Web_Console/Console_messages#Server
 */
class ChromeLogger implements LoggerInterface
{
	use SingletonTrait;

	/** @var string */
	const VERSION = '4.1.0';
	/** @var int */
	const BACKTRACE_LEVEL = 2;

	/** @var string */
	const GROUP = 'group';
	/** @var string */
	const GROUP_END = 'groupEnd';
	/** @var string */
	const GROUP_COLLAPSED = 'groupCollapsed';
	/** @var string */
	const TABLE = 'table';

	/** @var array */
	protected static $_json = [
		'version' => self::VERSION,
		'columns' => ['log', 'backtrace', 'type'],
		'rows' => [],
	];

	/** @var array */
	protected static $_backtraces = [];

	/**
	 * Prevent recursion when working with objects referring to each other
	 *
	 * @var array
	 */
	protected static $_processed = [];

	/**
	 * Sends message to ChromeLogger console.
	 *
	 * @param mixed
	 * @param string
	 *
	 * @return bool    was successful?
	 */
	public function log($message, $priority = self::DEBUG)
	{
		if (headers_sent()) {
			return false;
		}

		static $convert = [
			self::DEBUG => 'log',
			self::INFO => 'info',
			self::WARNING => 'warn',
			self::ERROR => 'error',
			self::EXCEPTION => 'error',
			self::CRITICAL => 'error',
		];

		$backtrace = 'unknown';
		if ($message instanceof \Exception) {
			$backtrace = $message->getFile() . ': ' . $message->getLine();
			$message = $message->getMessage();
		} else {
			$trace = debug_backtrace(false);
			$level = self::BACKTRACE_LEVEL;
			if (isset($trace[$level]['file'], $trace[$level]['line'])) {
				$backtrace = $trace[$level]['file'] . ': ' . $trace[$level]['line'];
			}
		}

		$type = $convert[$priority] ?? $priority;

		static::$_processed = [];
		$logs = [static::_convert($message)];

		static::_addRow($logs, $backtrace, $type);

		return true;
	}

	protected function init()
	{
		static::$_json['request_uri'] = $_SERVER['REQUEST_URI'];
	}

	/**
	 * converts an object to a better format for logging
	 *
	 * @param Object
	 *
	 * @return array
	 */
	protected static function _convert($object)
	{
		// if this isn't an object then just return it
		if (!is_object($object)) {
			return $object;
		}

		//Mark this object as processed so we don't convert it twice and it
		//Also avoid recursion when objects refer to each other
		static::$_processed[] = $object;
		$object_as_array = [];
		// first add the class name
		$reflection = new \ReflectionClass($object);
		$object_as_array['___class_name'] = $reflection->getName();
		// loop through the properties and add those
		foreach ($reflection->getProperties() as $property) {
			$type = static::_getPropertyKey($property);
			$property->setAccessible(true);
			$value = $property->getValue($object);
			// same instance as parent object
			if ($value === $object || in_array($value, static::$_processed, true)) {
				$value = 'recursion - parent object [' . get_class($value) . ']';
			}
			$object_as_array[$type] = static::_convert($value);
		}

		return $object_as_array;
	}

	/**
	 * takes a reflection property and returns a nicely formatted key of the property name
	 *
	 * @param \ReflectionProperty
	 *
	 * @return string
	 */
	protected static function _getPropertyKey(\ReflectionProperty $property)
	{
		$control = 'public';
		if ($property->isProtected()) {
			$control = 'protected';
		} else if ($property->isPrivate()) {
			$control = 'private';
		}
		$control .= $property->isStatic() ? ' static ' : ' ';

		return $control . $property->getName();
	}

	/**
	 * adds a value to the data array
	 *
	 * @var mixed
	 * @return void
	 */
	protected static function _addRow(array $logs, $backtrace, $type)
	{
		// if this is logged on the same line for example in a loop, set it to null to save space
		// for group, groupEnd, and groupCollapsed take out the backtrace since it is not useful
		if (
			in_array($backtrace, static::$_backtraces, true) ||
			in_array($type, [self::GROUP, self::GROUP_END, self::GROUP_COLLAPSED], true)
		) {
			$backtrace = null;
		} else if ($backtrace !== null) {
			static::$_backtraces[] = $backtrace;
		}

		static::$_json['rows'][] = [$logs, $backtrace, $type];

		$data = base64_encode(utf8_encode(json_encode(static::$_json)));
		header('X-ChromeLogger-Data: ' . $data);
	}
}

