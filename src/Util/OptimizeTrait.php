<?php

namespace Hail\Util;

trait OptimizeTrait
{
    protected static function optimizeGet(string $key, $file = null)
    {
        static $delay = null;
        if ($delay === null) {
            $delay = (int) \env('OPTIMIZE_CHECK_DELAY');
        }

        $prefix = static::class;
        if ($delay > 0 && $file !== null) {
            $time = $key . '|time';
            $check = Optimize::get($prefix, $time);
            $now = \time();
            if ($check !== false && $now >= ($check[0] + $delay)) {
                if (static::optimizeVerifyMTime($file, $check[1])) {
                    return false;
                }

                $check[0] = $now;
                Optimize::set($prefix, $time, $check);
            }
        }

        return Optimize::get(
            $prefix, $key
        );
    }

    protected static function optimizeSet($key, $value = null, $file = null)
    {
        if ($file !== null) {
            $mtime = static::optimizeFileMTime($file);
            if ($mtime !== []) {
                $key = [
                    $key => $value,
                    $key . '|time' => [\time(), $mtime],
                ];
            }
        }

        if (\is_array($key)) {
            return Optimize::setMultiple(
                static::class, $key
            );
        }

        return Optimize::set(
            static::class, $key, $value
        );
    }

    protected static function optimizeVerifyMTime($file, array $check): bool
    {
        if (!\is_array($file)) {
            $file = [$file];
        } else {
            $file = \array_unique($file);
        }

        foreach ($file as $v) {
            if (\file_exists($v)) {
                if (!isset($check[$v]) || \filemtime($v) !== $check[$v]) {
                    return true;
                }
            } elseif (isset($check[$v])) {
                return true;
            }

            unset($check[$v]);
        }

        return [] !== $check;
    }

    protected static function optimizeFileMTime($file): array
    {
        $file = \array_unique((array) $file);

        $mtime = [];
        foreach ($file as $v) {
            if (\file_exists($v)) {
                $mtime[$v] = \filemtime($v);
            }
        }

        return $mtime;
    }
}