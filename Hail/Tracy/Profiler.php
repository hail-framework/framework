<?php
namespace Hail\Tracy;

class Profiler
{
	const START_LABEL = 'start_label'; // string
	const START_TIME = 'start_time'; // float start time in seconds
	const START_MEMORY_USAGE = 'start_memory_usage'; // int amount of used memory at start in bytes
	const FINISH_LABEL = 'finish_label'; // string
	const FINISH_TIME = 'finish_time'; // float finish time in seconds
	const FINISH_MEMORY_USAGE = 'finish_memory_usage'; // int amount of used memory at finish in bytes
	const TIME_OFFSET = 'time_offset'; // float time offset in seconds
	const MEMORY_USAGE_OFFSET = 'memory_usage_offset'; // int amount of memory usage offset in bytes

	const DURATION = 'duration';
	const ABSOLUTE_DURATION = 'absolute_duration';
	const MEMORY_USAGE_CHANGE = 'memory_usage_change';
	const ABSOLUTE_MEMORY_USAGE_CHANGE = 'absolute_memory_usage_change';
	const TIME_LINE_BEFORE = 'time_line_before';
	const TIME_LINE_ACTIVE = 'time_line_active';
	const TIME_LINE_INACTIVE = 'time_line_inactive';
	const TIME_LINE_AFTER = 'time_line_after';

	/**
	 * @var array[]
	 */
	private static $profiles = [];

	/**
	 * @var bool
	 */
	protected static $enabled = false;
	/**
	 * @var array[]
	 */
	protected static $stack = [];

	/**
	 * Enable profiler
	 */
	public static function enable()
	{
		static::$enabled = true;
	}

	/**
	 * Disable profiler
	 */
	public static function disable()
	{
		static::$enabled = false;
	}

	/**
	 * @return bool true if profiler is enabled, otherwise false
	 */
	public static function isEnabled()
	{
		return static::$enabled;
	}

	/**
	 * Get current "{file}#{line}"
	 *
	 * @param array ...$args
	 *
	 * @return string|bool current "{file}#{line}" on success or false on failure
	 */
	public static function getCurrentFileHashLine(...$args)
	{
		$deep = &$args[0];
		$backtrace = debug_backtrace();
		$backtrace = &$backtrace[$deep ?: 0];
		if ($backtrace) {
			return "{$backtrace['file']}#{$backtrace['line']}";
		}

		return false;
	}

	/**
	 * Start profiling
	 *
	 * @param string $labelOrFormat
	 * @param mixed $args [optional]
	 * @param mixed $_ [optional]
	 *
	 * @return bool true on success or false on failure
	 */
	public static function start($labelOrFormat = null, $args = null, $_ = null)
	{
		if (static::$enabled) {
			if ($labelOrFormat === null) {
				$labelOrFormat = static::getCurrentFileHashLine(1);
				$args = null;
				$_ = null;
			}

			if (static::$enabled) {
				if ($args === null) {
					$label = $labelOrFormat;
				} else {
					/** @noinspection SpellCheckingInspection */
					$label = call_user_func_array('sprintf', func_get_args());
				}
				$now = microtime(true);
				$memoryUsage = memory_get_usage(true);
				$profile = [
					'meta' => [
						self::START_LABEL => $label,
						self::TIME_OFFSET => 0,
						self::MEMORY_USAGE_OFFSET => 0,
						self::START_TIME => $now,
						self::START_MEMORY_USAGE => $memoryUsage,
					],
				];
				static::$stack[] = $profile;

				return true;
			}
		}

		return false;
	}

	/**
	 * Finish profiling and get result
	 *
	 * @param string $labelOrFormat
	 * @param mixed $args [optional]
	 * @param mixed $_ [optional]
	 *
	 * @return bool|array profile on success or false on failure
	 * @throws \RuntimeException
	 */
	public static function finish($labelOrFormat = null, $args = null, $_ = null)
	{
		if (static::$enabled) {
			if ($labelOrFormat === null) {
				$labelOrFormat = static::getCurrentFileHashLine(1);
				$args = null;
				$_ = null;
			}

			$profile = false;
			if (static::$enabled) {
				$now = microtime(true);
				$memoryUsage = memory_get_usage(true);
				if ([] === static::$stack) {
					throw new \RuntimeException('The stack is empty. Call Hail\Tracy\Profiler::start() first.');
				}
				if ($args === null) {
					$label = $labelOrFormat;
				} else {
					/** @noinspection SpellCheckingInspection */
					$label = call_user_func_array('sprintf', func_get_args());
				}
				/** @var array $profile */
				$profile = array_pop(static::$stack);
				$profile['meta'][self::FINISH_LABEL] = $label;
				$profile['meta'][self::FINISH_TIME] = $now;
				$profile['meta'][self::FINISH_MEMORY_USAGE] = $memoryUsage;
				$profile[self::ABSOLUTE_DURATION] = $profile['meta'][self::FINISH_TIME] - $profile['meta'][self::START_TIME];
				$profile[self::DURATION] = $profile[self::ABSOLUTE_DURATION] - $profile['meta'][self::TIME_OFFSET];
				$profile[self::ABSOLUTE_MEMORY_USAGE_CHANGE] = $profile['meta'][self::FINISH_MEMORY_USAGE] - $profile['meta'][self::START_MEMORY_USAGE];
				$profile[self::MEMORY_USAGE_CHANGE] = $profile[self::ABSOLUTE_MEMORY_USAGE_CHANGE] - $profile['meta'][self::MEMORY_USAGE_OFFSET];
				if ([] !== static::$stack) {
					$prefix = &static::$stack[count(static::$stack) - 1]['meta'];
					$prefix[self::TIME_OFFSET] += $profile[self::ABSOLUTE_DURATION];
					$prefix[self::MEMORY_USAGE_OFFSET] += $profile[self::ABSOLUTE_MEMORY_USAGE_CHANGE];
				}
			}

			self::$profiles[] = $profile;

			return $profile;
		}

		return false;
	}

	public static function count()
	{
		return count(self::$profiles);
	}

	public static function getProfiles()
	{
		if (self::$profiles === []) {
			$t0 = 0;
			$tN = 0;
		} else {
			$t0 = self::$profiles[0]['meta'][self::START_TIME];
			$tN = self::$profiles[0]['meta'][self::FINISH_TIME];
			foreach (self::$profiles as $profile) {
				$t0 = min($t0, $profile['meta'][self::START_TIME]);
				$tN = max($tN, $profile['meta'][self::FINISH_TIME]);
			}
		}

		$time = max($tN - $t0, 0.001);
		foreach (self::$profiles as $profile) {
			$profile['meta'][self::TIME_LINE_BEFORE] = floor(($profile['meta'][self::START_TIME] - $t0) / $time * 100);
			$profile['meta'][self::TIME_LINE_ACTIVE] = floor($profile[self::DURATION] / $time * 100);
			$profile['meta'][self::TIME_LINE_INACTIVE] = floor(($profile[self::ABSOLUTE_DURATION] - $profile[self::DURATION]) / $time * 100);
			$profile['meta'][self::TIME_LINE_AFTER] = 100 - $profile['meta'][self::TIME_LINE_BEFORE] - $profile['meta'][self::TIME_LINE_ACTIVE] - $profile['meta'][self::TIME_LINE_INACTIVE];
			yield $profile;
		}
	}
}