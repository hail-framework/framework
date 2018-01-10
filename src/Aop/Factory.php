<?php

namespace Hail\Aop;


class Factory
{
    public const BEFORE = 'before';
    public const AFTER = 'after';
    public const BOTH = 'both';
    public const EXCEPTION = 'exception';

    /**
     * @var \SplObjectStorage
     */
    private static $proxies;

    /**
     * @var \SplObjectStorage
     */
    private static $advices;

    /**
     * @param Object $object 目标对象
     *
     * @return Proxy
     * @throws \InvalidArgumentException
     */
    public static function get($object): Proxy
    {
        if (self::$proxies === null) {
            self::$proxies = new \SplObjectStorage();
        }

        if (!isset(self::$proxies[$object])) {
            self::$proxies[$object] = new Proxy($object);
        }

        return self::$proxies[$object];
    }

    public static function getOrigin(Proxy $object)
    {
        foreach (self::$proxies as $obj => $proxy) {
            if ($proxy === $object) {
                return $obj;
            }
        }

        return null;
    }

    /**
     * @param string   $point
     * @param Object   $object
     * @param string   $method
     * @param callable $callable
     */
    private static function add(string $point, $object, string $method, callable $callable)
    {
        if ($object instanceof Proxy) {
            $object = self::getOrigin($object);

            if ($object === null) {
                throw new \InvalidArgumentException('Can not found origin object from AOP proxy instance!');
            }
        }

        $advices = self::getAdvice($object);

        $points = [$point];
        if ($point === self::BOTH) {
            $points = [self::BEFORE, self::AFTER];
        }

        foreach ($points as $p) {
            $advices[$method][$p] = $callable;
        }
    }

    public static function addBefore($object, string $method, callable $callable)
    {
        self::add(self::BEFORE, $object, $method, $callable);
    }

    public static function addAfter($object, string $method, callable $callable)
    {
        self::add(self::AFTER, $object, $method, $callable);
    }

    public static function addBoth($object, string $method, callable $callable)
    {
        self::add(self::BOTH, $object, $method, $callable);
    }

    public static function addException($object, string $method, callable $callable)
    {
        self::add(self::EXCEPTION, $object, $method, $callable);
    }

    /**
     * @param Object      $object
     * @param string|null $method
     * @param string|null $point
     *
     * @return iterable
     */
    public static function getAdvice($object, string $method = null, string $point = null): iterable
    {
        if (self::$advices === null) {
            self::$advices = new \SplObjectStorage();
        }

        $advices = self::$advices[$object] ?? [];

        if ($method !== null) {
            $advices = $advices[$method] ?? [];

            if ($point !== null) {
                $advices = $advices[$point] ?? [];
            }
        }

        return $advices;
    }

    /**
     * @param Param $param
     */
    public static function run(Param $param): void
    {
        $advices = self::getAdvice(
            $param->getObject(),
            $param->getMethod(),
            $param->getJointPoint()
        );

        foreach ($advices as $advice) {
            $advice($param);
        }
    }
}