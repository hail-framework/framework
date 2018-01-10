<?php
namespace Hail\Redis;


class Helpers
{
    /**
     * Flatten arguments
     *
     * If an argument is an array, the key is inserted as argument followed by the array values
     *  ['zrangebyscore', '-inf', 123, ['limit' => ['0', '1']]]
     * becomes
     *  ['zrangebyscore', '-inf', 123, 'limit', '0', '1']
     *
     * @param array $arguments
     * @param array $out
     *
     * @return array
     */
    public static function flattenArguments(array $arguments, array $out = []): array
    {
        foreach ($arguments as $key => $arg) {
            if (!\is_int($key)) {
                $out[] = $key;
            }

            if (\is_array($arg)) {
                $out = self::flattenArguments($arg, $out);
            } else {
                $out[] = $arg;
            }
        }

        return $out;
    }

    /**
     * Build the Redis unified protocol command
     *
     * @param array $args
     *
     * @return string
     */
    public static function prepareCommand(array $args)
    {
        $return = '*' . \count($args) . "\r\n";
        foreach ($args as $arg) {
            $return .= '$' . \strlen($arg) . "\r\n" . $arg . "\r\n";
        }

        return $return;
    }
}