<?php
/**
 * Some code from https://github.com/mindplay-dk/unbox
 *
 * @copyright Rasmus Schultz <http://blog.mindplay.dk/>
 */

namespace Hail\Container;

use Hail\Util\SingletonTrait;
use Psr\Container\ContainerInterface;

/**
 * Pseudo-namespace for some common reflection helper-functions.
 */
class Builder
{
    /**
     * Call any given callable, using dependency injection to satisfy it's arguments, and/or
     * manually specifying some of those arguments - then return the value from the call.
     *
     * This will work for any callable:
     *
     *     Builder::call($container, 'foo');               // function foo()
     *     Builder::call($container, $foo, 'baz');         // instance method $foo->baz()
     *     Builder::call($container, [Foo::class, 'bar']); // static method Foo::bar()
     *     Builder::call($container, $foo);                // closure (or class implementing __invoke)
     *
     * In any of those examples, you can also supply custom arguments, either named or
     * positional, or mixed, as per the `$map` argument in `register()`, `configure()`, etc.
     *
     * @see create() which lets you invoke any constructor.
     *
     * @param ContainerInterface                  $container
     * @param callable                            $callback any arbitrary closure or callable, or object implementing __invoke()
     * @param mixed|mixed[]                       $map      mixed list/map of parameter values (and/or boxed values)
     * @param \ReflectionParameter[]|array[]|null $params
     *
     * @return mixed return value from the given callable
     *
     * @throws \InvalidArgumentException
     */
    public static function call(
        ContainerInterface $container,
        callable $callback,
        array $map = [],
        array $params = null
    ) {
        $params = $params ?? static::createFromCallable($callback)->getParameters();
        if ($params !== []) {
            $params = static::resolve($container, $params, $map);

            return $callback(...$params);
        }

        return $callback();
    }

    /**
     * Create an instance of a given class.
     *
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param ContainerInterface                  $container
     * @param string                              $class fully-qualified class-name
     * @param mixed[]                             $map   mixed list/map of parameter values (and/or boxed values)
     * @param \ReflectionParameter[]|array[]|null $params
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public static function create(ContainerInterface $container, string $class, array $map = [], array $params = null)
    {
        if (!\class_exists($class)) {
            throw new \InvalidArgumentException("unable to create component: {$class} (autoloading failed)");
        }

        if (isset(\class_uses($class)[SingletonTrait::class])) {
            return $class::getInstance();
        }

        if ($params === null) {
            $reflection = new \ReflectionClass($class);

            if (!$reflection->isInstantiable()) {
                throw new \InvalidArgumentException("unable to create instance of abstract class: {$class}");
            }

            $constructor = $reflection->getConstructor();

            if ($constructor && ($params = $constructor->getParameters()) !== []) {
                $params = static::resolve($container, $params, $map, false);
            } else {
                $params = [];
            }

            return $reflection->newInstanceArgs($params);
        }

        if ($params !== []) {
            $params = static::resolve($container, $params, $map, false);
        }

        return new $class(...$params);
    }

    /**
     * Create a Reflection of the function references by any type of callable (or object implementing `__invoke()`)
     *
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param callable $callback
     *
     * @return \ReflectionFunctionAbstract
     *
     */
    public static function createFromCallable(callable $callback): \ReflectionFunctionAbstract
    {
        if (\is_array($callback)) {
            return new \ReflectionMethod($callback[0], $callback[1]);
        }

        if (\is_object($callback)) {
            return new \ReflectionMethod($callback, '__invoke');
        }

        return new \eflectionFunction($callback);
    }

    /**
     * Internally resolves parameters to functions or constructors.
     *
     * @param ContainerInterface             $container
     * @param \ReflectionParameter[]|array[] $params parameter reflections
     * @param array                          $map    mixed list/map of parameter values (and/or boxed values)
     * @param bool                           $safe   if TRUE, it's considered safe to resolve against parameter names
     *
     * @return array parameters
     *
     * @throws \InvalidArgumentException
     */
    protected static function resolve(
        ContainerInterface $container,
        array $params,
        array $map,
        bool $safe = true
    ): array {
        $args = [];
        foreach ($params as $index => $param) {
            $value = static::getParameterValue($container, $param, $index, $map, $safe);

            if ($value instanceof \Closure) {
                $value = $value($container); // unbox a boxed value
            }

            $args[] = $value; // argument resolved!
        }

        return $args;
    }

    /**
     * @param ContainerInterface         $container
     * @param \ReflectionParameter|array $param
     * @param int                        $index
     * @param array                      $map
     * @param bool                       $safe
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected static function getParameterValue(
        ContainerInterface $container,
        $param,
        int $index,
        array $map,
        bool $safe
    ) {
        if ($isReflection = ($param instanceof \ReflectionParameter)) {
            $name = $param->name;
        } elseif (\is_array($param)) {
            $name = $param['name'];
        } else {
            throw new \InvalidArgumentException('Parameter must be the instance of \ReflectionParameter or array');
        }

        if (\array_key_exists($name, $map)) {
            return $map[$name]; // // resolve as user-provided named argument
        }

        if (\array_key_exists($index, $map)) {
            return $map[$index]; // resolve as user-provided positional argument
        }

        $type = $isReflection ? static::getParameterType($param) : $param['type'];

        if ($type) {
            if (\array_key_exists($type, $map)) {
                return $map[$type]; // resolve as user-provided type-hinted argument
            }

            if ($container->has($type)) {
                return $container->get($type); // resolve as component registered by class/interface name
            }
        }

        if ($safe && $container->has($name)) {
            return $container->get($name); // resolve as component with matching parameter name
        }

        if ($isReflection) {
            if ($param->isOptional()) {
                return $param->getDefaultValue(); // unresolved, optional: resolve using default value
            }

            if ($type && $param->allowsNull()) {
                return null; // unresolved, type-hinted, nullable: resolve as NULL
            }

            $reflection = $param->getDeclaringFunction();
            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();
        } elseif (\array_key_exists('default', $param)) {
            return $param['default'];
        } else {
            ['file' => $file, 'line' => $line] = $param;
        }

        // unresolved - throw a container exception:
        throw new \InvalidArgumentException(
            "Unable to resolve parameter: \${$name} " . ($type ? "({$type}) " : '') .
            'in file: ' . $file . ', line ' . $line
        );
    }

    /**
     * Obtain the type-hint of a `ReflectionParameter`, but avoid triggering autoload (as a performance optimization)
     *
     * @param \ReflectionParameter $param
     *
     * @return string|null fully-qualified type-name (or NULL, if no type-hint was available)
     */
    public static function getParameterType(\ReflectionParameter $param): ?string
    {
        if (!$param->hasType()) {
            return null;
        }

        $type = $param->getType();

        if ($type === null || $type->isBuiltin()) {
            return null;
        }

        $type = $type->getName();
        $lower = \strtolower($type);

        if ($lower === 'self') {
            return $param->getDeclaringClass()->getName();
        }

        if ($lower === 'parent' && ($parent = $param->getDeclaringClass()->getParentClass())) {
            return $parent->getName();
        }

        return $type;
    }

    /**
     * @param callable $callback
     * @param bool     $toArray
     *
     * @return \ReflectionParameter[]|array[]
     */
    public static function getCallableParameters(callable $callback, bool $toArray = false): array
    {
        $params = self::createFromCallable($callback)->getParameters();

        if ($params === []) {
            return [];
        }

        if (!$toArray) {
            return $params;
        }

        return static::reflectionParameterToArray($params);
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param string $class
     * @param bool   $toArray
     *
     * @return \ReflectionParameter[]|array[]
     */
    public static function getClassParameters(string $class, bool $toArray = false): array
    {
        if (!\class_exists($class)) {
            throw new \InvalidArgumentException("Class not exists: {$class} (autoloading failed)");
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException("Unable to create instance of abstract class: {$class}");
        }

        $params = $reflection->getConstructor()->getParameters();

        if ($params === []) {
            return [];
        }

        if (!$toArray) {
            return $params;
        }

        return static::reflectionParameterToArray($params);
    }

    /**
     * @param \ReflectionParameter[] $params
     *
     * @return array[]
     */
    protected static function reflectionParameterToArray(array $params): array
    {
        $return = [];
        foreach ($params as $param) {
            $array = [
                'name' => $param->name,
                'type' => self::getParameterType($param),
            ];

            if ($param->isOptional()) {
                $array['default'] = $param->getDefaultValue();
            } elseif ($array['type'] && $param->allowsNull()) {
                $array['default'] = null;
            }

            $reflection = $param->getDeclaringFunction();
            $array['file'] = $reflection->getFileName();
            $array['line'] = $reflection->getStartLine();

            $return[] = $array;
        }

        return $return;
    }
}
