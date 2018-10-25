<?php
/**
 * Some code from https://github.com/mindplay-dk/unbox
 *
 * @copyright Rasmus Schultz <http://blog.mindplay.dk/>
 */

namespace Hail\Container;

use Hail\Util\ArrayTrait;
use Psr\Container\ContainerInterface;
use Hail\Container\Exception\{
    InvalidArgumentException,
    NotFoundException
};

/**
 * This class implements a simple dependency injection container.
 */
class Container implements ContainerInterface, \ArrayAccess
{
    use ArrayTrait;

    /**
     * @var mixed[] map where component name => value
     */
    protected $values = [];

    /**
     * @var callable[] map where component name => factory function
     */
    protected $factory = [];

    /**
     * @var array map where component name => mixed list/map of parameter names
     */
    protected $factoryMap = [];

    /**
     * @var callable[][] map where component name => list of configuration functions
     */
    protected $config = [];

    /**
     * @var array[][] map where component name => mixed list/map of parameter names
     */
    protected $configMap = [];

    /**
     * @var bool[] map where component name => TRUE, if the component has been initialized
     */
    protected $active = [];

    /**
     * @var string[]
     */
    protected $alias = [];

    /**
     * @var string[][]
     */
    protected $abstractAlias = [];

    public function __construct()
    {
        foreach (
            [
                'di',
                'container',
                __CLASS__,
                static::class,
                ContainerInterface::class,
            ] as $v
        ) {
            $this->values[$v] = $this;
            $this->active[$v] = true;
        }
    }

    /**
     * Resolve the registered component with the given name.
     *
     * @param string $name component name
     *
     * @return mixed
     *
     * @throws NotFoundException
     * @throws InvalidArgumentException
     */
    public function get($name)
    {
        switch (true) {
            case isset($this->active[$name]):
                return $this->values[$name];

            case isset($this->values[$name]):
                break;

            case \array_key_exists($name, $this->values):
                break;

            case isset($this->alias[$name]):
                $this->active[$name] = true;

                return $this->values[$name] = $this->get($this->alias[$name]);

            case isset($this->factory[$name]):
                $factory = $this->factory[$name];

                if (\is_string($factory) && \class_exists($factory)) {
                    $this->values[$name] = $this->create($factory, $this->factoryMap[$name]);
                } else {
                    $this->values[$name] = $this->call($factory, $this->factoryMap[$name]);
                }
                break;

            case \class_exists($name):
                return $this->build($name);

            default:
                throw new NotFoundException($name);
        }

        $this->active[$name] = true;


        if (null !== $configures = $this->getConfigure($name)) {
            foreach ($configures as $index => $config) {
                $value = $this->call($config, $this->configMap[$name][$index]);

                if ($value !== null) {
                    $this->values[$name] = $value;
                }
            }
        }

        return $this->values[$name];
    }


    /**
     * Check for the existence of a component with a given name.
     *
     * @param string $name component name
     *
     * @return bool true, if a component with the given name has been defined
     */
    public function has($name): bool
    {
        return isset($this->values[$name]) ||
            isset($this->factory[$name]) ||
            isset($this->alias[$name]) ||
            \array_key_exists($name, $this->values);
    }


    /**
     * @see Builder::call()
     *
     * @param callable                            $callback
     * @param mixed|mixed[]                       $map
     * @param \ReflectionParameter[]|array[]|null $params
     *
     * @return mixed return value from the given callable
     *
     * @throws InvalidArgumentException
     */
    public function call(callable $callback, array $map = [], array $params = null)
    {
        try {
            return Builder::call($this, $callback, $map, $params);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
    }

    /**
     * @see Builder::create()
     *
     * @param string                              $class
     * @param mixed[]                             $map
     * @param \ReflectionParameter[]|array[]|null $params
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function create(string $class, array $map = [], array $params = null)
    {
        try {
            return Builder::create($this, $class, $map, $params);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
    }

    /**
     * Dynamically inject a component into this Container.
     *
     * Enables implementation of "auto-wiring" patterns, where missing components are injected
     * at run-time into a live `Container` instance.
     *
     * You should always test with {@see has()} prior to injecting a component - attempting to
     * override an existing component will generate an exception.
     *
     * @throws InvalidArgumentException if the specified component has already been defined
     *
     * @param string $name component name
     * @param mixed  $value
     */
    public function inject(string $name, $value): void
    {
        if ($this->has($name)) {
            throw new InvalidArgumentException("Attempted override of existing component: {$name}");
        }

        $this->values[$name] = $value;
        $this->active[$name] = true;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @throws InvalidArgumentException if the specified component not initialized
     */
    public function replace(string $name, $value)
    {
        if (!isset($this->active[$name])) {
            throw new InvalidArgumentException("The component not initialized: {$name}");
        }

        $this->values[$name] = $value;

        if (!isset($this->abstractAlias[$name])) {
            return;
        }

        foreach ($this->abstractAlias[$name] as $alias) {
            if (isset($this->active[$alias])) {
                $this->values[$alias] = $value;
            }
        }
    }

    /**
     * Register a component for dependency injection.
     *
     * There are numerous valid ways to register components.
     *
     *   * `register(Foo::class)` registers a component by it's class-name, and will try to
     *     automatically resolve all of it's constructor arguments.
     *
     *   * `register(Foo::class, ['bar'])` registers a component by it's class-name, and will
     *     use `'bar'` as the first constructor argument, and try to resolve the rest.
     *
     *   * `register(Foo::class, [$container->ref(Bar::class)])` creates a boxed reference to
     *     a registered component `Bar` and provides that as the first argument.
     *
     *   * `register(Foo::class, ['bat' => 'zap'])` registers a component by it's class-name
     *     and will use `'zap'` for the constructor argument named `$bat`, and try to resolve
     *     any other arguments.
     *
     *   * `register(Bar::class, Foo::class)` registers a component `Foo` under another name
     *     `Bar`, which might be an interface or an abstract class.
     *
     *   * `register(Bar::class, Foo::class, ['bar'])` same as above, but uses `'bar'` as the
     *     first argument.
     *
     *   * `register(Bar::class, Foo::class, ['bat' => 'zap'])` same as above, but, well, guess.
     *
     *   * `register(Bar::class, function (Foo $foo) { return new Bar(...); })` registers a
     *     component with a custom creation function.
     *
     *   * `register(Bar::class, function ($name) { ... }, [$container->ref('db.name')]);`
     *     registers a component creation function with a reference to a component "db.name"
     *     as the first argument.
     *
     * In effect, you can think of `$func` as being an optional argument.
     *
     * The provided parameter values may include any `\Closure`, such as the boxed
     * component referenced created by {@see Container::ref()} - these will be unboxed as late
     * as possible.
     *
     * @param string                      $name                component name
     * @param callable|mixed|mixed[]|null $define              creation function or class-name, or, if the first
     *                                                         argument is a class-name, a map of constructor arguments
     * @param array                       $map                 mixed list/map of parameter values (and/or boxed values)
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function register(string $name, $define = null, array $map = []): void
    {
        if (isset($this->active[$name])) {
            throw new InvalidArgumentException("Attempted override of existing component: {$name}");
        }

        if ($define instanceof \Closure) {
            $func = $define;
        } elseif (\is_callable($define)) {
            // second argument is a creation function
            $func = \Closure::fromCallable($define);
        } elseif (\is_string($define)) {
            // second argument is a class-name
            $func = $define;
        } elseif (\is_array($define)) {
            $func = $name;
            $map = $define;
        } elseif (null === $define) {
            // first argument is both the component and class-name
            $func = $name;
            $map = [];
        } else {
            throw new InvalidArgumentException('Unexpected argument type for $define: ' . \gettype($define));
        }

        $this->factory[$name] = $func;
        $this->factoryMap[$name] = $map;

        unset($this->values[$name]);
    }

    /**
     * Directly inject a component into the container - use this to register components that
     * have already been created for some reason; for example, the Composer ClassLoader.
     *
     * @param string $name component name
     * @param mixed  $value
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function set(string $name, $value): void
    {
        if (isset($this->active[$name])) {
            throw new InvalidArgumentException("Attempted override of existing component: {$name}");
        }

        $this->values[$name] = $value;

        unset(
            $this->factory[$name],
            $this->factoryMap[$name],
            $this->alias[$name]
        );

        $this->removeAbstractAlias($name);
    }

    /**
     * Register a component as an alias of another registered component.
     *
     * @param string $alias    new component name
     * @param string $abstract referenced existing component name
     *
     * @throws InvalidArgumentException
     */
    public function alias(string $alias, string $abstract): void
    {
        if (\array_key_exists($alias, $this->values) || isset($this->factory[$alias])) {
            throw new InvalidArgumentException("Already defined in container: $alias");
        }

        if ($alias === $abstract) {
            throw new InvalidArgumentException('Alias cannot be the same as the original name');
        }

        $this->alias[$alias] = $abstract;

        if (!isset($this->abstractAlias[$abstract])) {
            $this->abstractAlias[$abstract] = [];
        }

        $this->abstractAlias[$abstract][] = $alias;
    }

    /**
     * Register a configuration function, which will be applied as late as possible, e.g.
     * on first use of the component. For example:
     *
     *     $container->configure('stack', function (MiddlewareStack $stack) {
     *         $stack->push(new MoreAwesomeMiddleware());
     *     });
     *
     * The given configuration function should include the configured component as the
     * first parameter to the closure, but may include any number of parameters, which
     * will be resolved and injected.
     *
     * The first argument (component name) is optional - that is, the name can be inferred
     * from a type-hint on the first parameter of the closure, so the following will work:
     *
     *     $container->register(PageLayout::class);
     *
     *     $container->configure(function (PageLayout $layout) {
     *         $layout->title = "Welcome";
     *     });
     *
     * In some cases, you may wish to fetch additional dependencies, by using additional
     * arguments, and specifying how these should be resolved, e.g. using
     * {@see Container::ref()} - for example:
     *
     *     $container->register("cache", FileCache::class);
     *
     *     $container->configure(
     *         "cache",
     *         function (FileCache $cache, $path) {
     *             $cache->setPath($path);
     *         },
     *         ['path' => $container->ref('cache.path')]
     *     );
     *
     * You can also use `configure()` to decorate objects, or manipulate (or replace) values:
     *
     *     $container->configure('num_kittens', function ($num_kittens) {
     *         return $num_kittens + 6; // add another litter
     *     });
     *
     * In other words, if your closure returns something, the component will be replaced.
     *
     * @param string|callable        $name         component name
     *                                             (or callable, if name is left out)
     * @param callable|mixed|mixed[] $func         `function (Type $component, ...) : void`
     *                                             (or parameter values, if name is left out)
     * @param mixed|mixed[]          $map          mixed list/map of parameter values and/or boxed values
     *                                             (or unused, if name is left out)
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     */
    public function configure($name, $func = null, $map = []): void
    {
        if (\is_callable($name)) {
            $map = $func ?: [];
            $func = $name;

            // no component name supplied, infer it from the closure:

            if ($func instanceof \Closure) {
                $param = new \ReflectionParameter($func, 0); // shortcut reflection for closures (as an optimization)
            } else {
                $param = Builder::getCallableParameters($func)[0];
            }

            $name = Builder::getParameterType($param); // infer component name from type-hint

            if ($name === null) {
                throw new InvalidArgumentException('No component-name or type-hint specified');
            }
        } elseif ($map === [] || !\array_key_exists(0, $map)) {
            $map[0] = $this->ref($name);
        }

        if (isset($this->active[$name])) {
            throw new InvalidArgumentException('Component already initialized');
        }

        $this->config[$name][] = $func;
        $this->configMap[$name][] = $map;
    }

    /**
     * @param string $name
     *
     * @return array|null
     */
    protected function getConfigure(string $name): ?array
    {
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }

        if (!isset($this->abstractAlias[$name])) {
            return null;
        }

        foreach ($this->abstractAlias[$name] as $alias) {
            if (isset($this->config[$alias])) {
                return $this->config[$alias];
            }
        }

        return null;
    }

    /**
     * Creates a boxed reference to a component with a given name.
     *
     * You can use this in conjunction with `register()` to provide a component reference
     * without expanding that reference until first use - for example:
     *
     *     $container->register(UserRepo::class, [$container->ref('cache')]);
     *
     * This will reference the "cache" component and provide it as the first argument to the
     * constructor of `UserRepo` - compared with using `$container->get('cache')`, this has
     * the advantage of not actually activating the "cache" component until `UserRepo` is
     * used for the first time.
     *
     * Another reason (besides performance) to use references, is to defer the reference:
     *
     *     $container->register(FileCache::class, ['root_path' => $container->ref('cache.path')]);
     *
     * In this example, the component "cache.path" will be fetched from the container on
     * first use of `FileCache`, giving you a chance to configure "cache.path" later.
     *
     * @param string $name component name
     *
     * @return mixed|\Closure component reference
     */
    public function ref(string $name)
    {
        if (isset($this->active[$name])) {
            return $this->values[$name];
        }

        return function () use ($name) {
            return $this->get($name);
        };
    }

    /**
     * @param string $name
     */
    public function delete(string $name): void
    {
        unset(
            $this->values[$name],
            $this->factory[$name],
            $this->factoryMap[$name],
            $this->alias[$name]
        );

        $this->removeAbstractAlias($name);
    }

    /**
     * Remove an alias from the contextual binding alias cache.
     *
     * @param  string $searched
     *
     * @return void
     */
    protected function removeAbstractAlias($searched): void
    {
        if (!isset($this->alias[$searched])) {
            return;
        }

        foreach ($this->abstractAlias as $abstract => $aliases) {
            foreach ($aliases as $index => $alias) {
                if ($alias === $searched) {
                    unset($this->abstractAlias[$abstract][$index]);
                }
            }
        }
    }

    public function __call(string $name, array $arguments)
    {
        return $this->get($name);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function build(string $key)
    {
        if ($this->has($key)) {
            $object = $this->get($key);
        } else {
            $object = $this->create($key);
            $this->inject($key, $object);
        }

        return $object;
    }
}
