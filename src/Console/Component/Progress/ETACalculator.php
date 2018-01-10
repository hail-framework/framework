<?php
namespace Hail\Console\Component\Progress;

class ETACalculator
{
    public static function calculateRemainingSeconds($proceeded, $total, $start, $now)
    {
        $secondDiff = ($now - $start);
        $speed = $secondDiff > 0 ? $proceeded / $secondDiff : 0;
        $remaining = $total - $proceeded;
        if ($speed > 0) {
            return $remaining / $speed;
        }

        return null;
    }

    public static function calculateEstimatedPeriod($proceeded, $total, $start, $now)
    {
        $str = '--';
        if ($remainingSeconds = self::calculateRemainingSeconds($proceeded, $total, $start, $now)) {
            $str = '';

            $days = 0;
            $hours = 0;
            $minutes = 0;
            if ($remainingSeconds > 86400) {
                $days = ceil($remainingSeconds / 86400);
                $remainingSeconds %= 86400;
            }

            if ($remainingSeconds > 3600) {
                $hours = ceil($remainingSeconds / 3600);
                $remainingSeconds %= 3600;
            }

            if ($remainingSeconds > 60) {
                $minutes = ceil($remainingSeconds / 60);
                $remainingSeconds %= 60;
            }

            if ($days > 0) {
                $str .= $days . 'd';
            }
            if ($hours) {
                $str .= $hours . 'h';
            }
            if ($minutes) {
                $str .= $minutes . 'm';
            }
            if ($remainingSeconds > 0) {
                $str .= ((int) $remainingSeconds) . 's';
            }
        }
        return $str;
    }

    public static function calculateEstimatedTime($proceeded, $total, $start, $now)
    {
        if ($remainingSeconds = self::calculateRemainingSeconds($proceeded, $total, $start, $now)) {
            return $now + $remainingSeconds;
        }

        return null;
    }
}
