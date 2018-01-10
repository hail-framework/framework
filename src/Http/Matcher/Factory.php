<?php

namespace Hail\Http\Matcher;

use Psr\Http\Message\RequestInterface;

/**
 * Class Factory
 *
 * @package Hail\Http\Matcher
 * @method static Accept accept(string $accept)
 * @method static Callback callback(callback $callback)
 * @method static Methods methods(string $method1, string $method2 = null, string $_ = null)
 * @method static Host host(string $host)
 * @method static Path path(string $path)
 * @method static Schemes schemes(string $scheme1, string $scheme2 = null, string $_ = null)
 */
final class Factory
{
    /**
     * @param mixed            $matchers
     * @param RequestInterface $request
     *
     * @return bool
     */
    public static function matches($matchers, RequestInterface $request): bool
    {
        $matchers = (array) $matchers;

        foreach ($matchers as $v) {
            if ($v === true) {
                continue;
            }

            if ($v === false) {
                return false;
            }

            if (\is_string($v)) {
                $len = \strlen($v);
                if ($len > 3 &&
                    $v[0] === '<' &&
                    ($pos = \strpos($v, ':')) !== false &&
                    $v[$len - 1] === '>'
                ) {
                    $fun = \trim(\substr($v, 1, $pos));
                    $args = \array_map('\trim', \explode(',', \substr($v, $pos + 1, -1)));

                    $matcher = self::$fun(...$args);
                } else {
                    $matcher = self::path($v);
                }
            } elseif (\is_callable($v)) {
                $matcher = self::callback($v);
            } else {
                $matcher = $v;
            }

            if (!$matcher instanceof MatcherInterface) {
                throw new \RuntimeException('Invalid matcher. Must be a boolean, string, callable or a Hail\\Http\\Matcher\\MatcherInterface');
            }

            if (!$matcher->matches($request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return MatcherInterface
     */
    public static function __callStatic(string $name, array $arguments): MatcherInterface
    {
        if (\count($arguments) === 1) {
            $arguments = $arguments[0];
        }

        $class = __NAMESPACE__ . '\\' . \ucfirst($name);

        return new $class($arguments);
    }
}