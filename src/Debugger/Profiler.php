<?php

namespace Hail\Debugger;

class Profiler
{
    public const START_LABEL = 'start_label'; // string
    public const START_TIME = 'start_time'; // float start time in seconds
    public const START_MEMORY_USAGE = 'start_memory_usage'; // int amount of used memory at start in bytes
    public const FINISH_LABEL = 'finish_label'; // string
    public const FINISH_TIME = 'finish_time'; // float finish time in seconds
    public const FINISH_MEMORY_USAGE = 'finish_memory_usage'; // int amount of used memory at finish in bytes
    public const TIME_OFFSET = 'time_offset'; // float time offset in seconds
    public const MEMORY_USAGE_OFFSET = 'memory_usage_offset'; // int amount of memory usage offset in bytes

    public const DURATION = 'duration';
    public const ABSOLUTE_DURATION = 'absolute_duration';
    public const MEMORY_USAGE_CHANGE = 'memory_usage_change';
    public const ABSOLUTE_MEMORY_USAGE_CHANGE = 'absolute_memory_usage_change';
    public const TIME_LINE_BEFORE = 'time_line_before';
    public const TIME_LINE_ACTIVE = 'time_line_active';
    public const TIME_LINE_INACTIVE = 'time_line_inactive';
    public const TIME_LINE_AFTER = 'time_line_after';

    /**
     * @var array[]
     */
    private static $profiles = [];

    /**
     * @var array[]
     */
    protected static $stack = [];

    /**
     * Get current "{file}#{line}"
     *
     * @param int $deep
     *
     * @return string|null current "{file}#{line}" on success or null on failure
     */
    public static function getCurrentFileHashLine(int $deep = 0): ?string
    {
        $backtrace = \debug_backtrace();
        if (!isset($backtrace[$deep])) {
            return null;
        }

        $backtrace = $backtrace[$deep];
        return "{$backtrace['file']}#{$backtrace['line']}";
    }

    /**
     * Start profiling
     *
     * @param array ...$args
     *
     * @return bool true on success or false on failure
     */
    public static function start(...$args)
    {
        if (Debugger::isProductionMode()) {
            return false;
        }

        if (!isset($args[1])) {
            $label = $args[0] ?? static::getCurrentFileHashLine(1);
        } else {
            $label = \sprintf(...$args);
        }

        $now = \microtime(true);
        $memoryUsage = \memory_get_usage(true);
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

    /**
     * Finish profiling and get result
     *
     * @param array ...$args
     *
     * @return bool|array profile on success or false on failure
     * @throws \RuntimeException
     */
    public static function finish(...$args)
    {
        if (Debugger::isProductionMode()) {
            return false;
        }

        if ([] === static::$stack) {
            throw new \RuntimeException('The stack is empty. Call Hail\Debugger\Profiler::start() first.');
        }

        $now = \microtime(true);
        $memoryUsage = \memory_get_usage(true);

        if (!isset($args[1])) {
            $label = $args[0] ?? static::getCurrentFileHashLine(1);
        } else {
            $label = \sprintf(...$args);
        }

        /** @var array $profile */
        $profile = \array_pop(static::$stack);
        $profile['meta'][self::FINISH_LABEL] = $label;
        $profile['meta'][self::FINISH_TIME] = $now;
        $profile['meta'][self::FINISH_MEMORY_USAGE] = $memoryUsage;
        $profile[self::ABSOLUTE_DURATION] = $profile['meta'][self::FINISH_TIME] - $profile['meta'][self::START_TIME];
        $profile[self::DURATION] = $profile[self::ABSOLUTE_DURATION] - $profile['meta'][self::TIME_OFFSET];
        $profile[self::ABSOLUTE_MEMORY_USAGE_CHANGE] = $profile['meta'][self::FINISH_MEMORY_USAGE] - $profile['meta'][self::START_MEMORY_USAGE];
        $profile[self::MEMORY_USAGE_CHANGE] = $profile[self::ABSOLUTE_MEMORY_USAGE_CHANGE] - $profile['meta'][self::MEMORY_USAGE_OFFSET];
        if ([] !== static::$stack) {
            $prefix = &static::$stack[\count(static::$stack) - 1]['meta'];
            $prefix[self::TIME_OFFSET] += $profile[self::ABSOLUTE_DURATION];
            $prefix[self::MEMORY_USAGE_OFFSET] += $profile[self::ABSOLUTE_MEMORY_USAGE_CHANGE];
        }

        self::$profiles[] = $profile;

        return $profile;
    }

    public static function count()
    {
        return \count(self::$profiles);
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
                $t0 = \min($t0, $profile['meta'][self::START_TIME]);
                $tN = \max($tN, $profile['meta'][self::FINISH_TIME]);
            }
        }

        $time = \max($tN - $t0, 0.001);
        foreach (self::$profiles as $profile) {
            $profile['meta'][self::TIME_LINE_BEFORE] = floor(($profile['meta'][self::START_TIME] - $t0) / $time * 100);
            $profile['meta'][self::TIME_LINE_ACTIVE] = floor($profile[self::DURATION] / $time * 100);
            $profile['meta'][self::TIME_LINE_INACTIVE] = floor(($profile[self::ABSOLUTE_DURATION] - $profile[self::DURATION]) / $time * 100);
            $profile['meta'][self::TIME_LINE_AFTER] = 100 - $profile['meta'][self::TIME_LINE_BEFORE] - $profile['meta'][self::TIME_LINE_ACTIVE] - $profile['meta'][self::TIME_LINE_INACTIVE];
            yield $profile;
        }
    }
}