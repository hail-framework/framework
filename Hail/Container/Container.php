<?php

namespace Hail\Container;

use Hail\Util\ArrayTrait;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;
use Hail\Container\Exception\{
	ContainerException,
	NotFoundException
};

/**
 * This class implements a simple dependency injection container.
 */
class Container implements ContainerInterface
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
	protected $factory_map = [];

	/**
	 * @var callable[][] map where component name => list of configuration functions
	 */
	protected $config = [];

	/**
	 * @var array[][] map where component name => mixed list/map of parameter names
	 */
	protected $config_map = [];

	/**
	 * @var bool[] map where component name => TRUE, if the component has been initialized
	 */
	protected $active = [];

	/**
	 * @var string[]
	 */
	protected $alias = [];

	public function __construct()
	{
		$this->values = [
			'di' => $this,
			'container' => $this,
			static::class => $this,
			ContainerInterface::class => $this
		];
	}

	/**
	 * Resolve the registered component with the given name.
	 *
	 * @param string $name component name
	 *
	 * @return mixed
	 *
	 * @throws ContainerException
	 * @throws NotFoundException
	 */
	public function get($name)
	{
		switch (true) {
			case isset($this->active[$name]):
				return $this->values[$name];

			case array_key_exists($name, $this->values):
				break;

			case isset($this->alias[$name]):
				$this->active[$name] = true;

				return $this->values[$name] = $this->get($this->alias[$name]);

			case isset($this->factory[$name]):
				$factory = $this->factory[$name];

				if (is_string($factory)) {
					$this->values[$name] = $this->create($factory, $this->factory_map[$name]);
				} else {
					$reflection = new \ReflectionFunction($factory);

					if (($params = $reflection->getParameters()) !== []) {
						$params = $this->resolve($params, $this->factory_map[$name]);
					}

					$this->values[$name] = $factory(...$params);
				}
				break;

			default:
				throw new NotFoundException($name);
		}

		$this->active[$name] = true;
		$this->initialize($name);

		return $this->values[$name];
	}

	/**
	 * Check for the existence of a component with a given name.
	 *
	 * @param string $name component name
	 *
	 * @return bool true, if a component with the given name has been defined
	 */
	public function has($name)
	{
		return isset($this->values[$name]) ||
			isset($this->factory[$name]) ||
			isset($this->alias[$name]) ||
			array_key_exists($name, $this->values);
	}


	/**
	 * Call any given callable, using dependency injection to satisfy it's arguments, and/or
	 * manually specifying some of those arguments - then return the value from the call.
	 *
	 * This will work for any callable:
	 *
	 *     $container->call('foo');               // function foo()
	 *     $container->call($foo, 'baz');         // instance method $foo->baz()
	 *     $container->call([Foo::class, 'bar']); // static method Foo::bar()
	 *     $container->call($foo);                // closure (or class implementing __invoke)
	 *
	 * In any of those examples, you can also supply custom arguments, either named or
	 * positional, or mixed, as per the `$map` argument in `register()`, `configure()`, etc.
	 *
	 * See also {@see create()} which lets you invoke any constructor.
	 *
	 * @param callable|object $callback any arbitrary closure or callable, or object implementing __invoke()
	 * @param mixed|mixed[]   $map      mixed list/map of parameter values (and/or boxed values)
	 *
	 * @return mixed return value from the given callable
	 */
	public function call($callback, $map = [])
	{
		$params = Reflection::createFromCallable($callback)->getParameters();
		if ($params !== []) {
			$params = $this->resolve($params, $map);
		}

		return $callback(...$params);
	}

	/**
	 * Create an instance of a given class.
	 *
	 * The container will internally resolve and inject any constructor arguments
	 * not explicitly provided in the (optional) second parameter.
	 *
	 * @param string        $class_name fully-qualified class-name
	 * @param mixed|mixed[] $map        mixed list/map of parameter values (and/or boxed values)
	 *
	 * @return mixed
	 *
	 * @throws ContainerException
	 */
	public function create($class_name, $map = [])
	{
		if (!class_exists($class_name)) {
			throw new ContainerException("unable to create component: {$class_name}");
		}

		$reflection = new \ReflectionClass($class_name);

		if (!$reflection->isInstantiable()) {
			throw new ContainerException("unable to create instance of abstract class: {$class_name}");
		}

		$constructor = $reflection->getConstructor();

		if ($constructor && ($params = $constructor->getParameters()) !== []) {
			$params = $this->resolve($params, $map, false);
		} else {
			$params = [];
		}

		return $reflection->newInstanceArgs($params);
	}

	/**
	 * Internally resolves parameters to functions or constructors.
	 *
	 * This is the heart of the beast.
	 *
	 * @param \ReflectionParameter[] $params parameter reflections
	 * @param array                  $map    mixed list/map of parameter values (and/or boxed values)
	 * @param bool                   $safe   if TRUE, it's considered safe to resolve against parameter names
	 *
	 * @return array parameters
	 *
	 * @throws ContainerException
	 */
	protected function resolve(array $params, $map, $safe = true)
	{
		$args = [];

		foreach ($params as $index => $param) {
			$name = $param->name;

			if (isset($map[$name]) || array_key_exists($name, $map)) {
				$value = $map[$name]; // // resolve as user-provided named argument
			} elseif (isset($map[$index]) || array_key_exists($index, $map)) {
				$value = $map[$index]; // resolve as user-provided positional argument
			} else {
				// as on optimization, obtain the argument type without triggering autoload:

				$type = Reflection::getParameterType($param);

				if ($type && isset($map[$type])) {
					$value = $map[$type]; // resolve as user-provided type-hinted argument
				} elseif ($type && $this->has($type)) {
					$value = $this->get($type); // resolve as component registered by class/interface name
				} elseif ($safe && $this->has($name)) {
					$value = $this->get($name); // resolve as component with matching parameter name
				} elseif ($param->isOptional()) {
					$value = $param->getDefaultValue(); // unresolved, optional: resolve using default value
				} elseif ($type && $param->allowsNull()) {
					$value = null; // unresolved, type-hinted, nullable: resolve as NULL
				} else {
					// unresolved - throw a container exception:

					$reflection = $param->getDeclaringFunction();

					throw new ContainerException(
						"Unable to resolve parameter: \${$name} " . ($type ? "({$type}) " : '') .
						'in file: ' . $reflection->getFileName() . ', line ' . $reflection->getStartLine()
					);
				}
			}

			if ($value instanceof \Closure) {
				$value = $value($this); // unbox a boxed value
			}

			$args[] = $value; // argument resolved!
		}

		return $args;
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
	public function inject($name, $value)
	{
		if ($this->has($name)) {
			throw new InvalidArgumentException("Attempted override of existing component: {$name}");
		}

		$this->values[$name] = $value;
		$this->active[$name] = true;
	}

	/**
	 * Internally initialize an active component.
	 *
	 * @param string $name component name
	 *
	 * @return void
	 *
	 * @throws ContainerException
	 */
	private function initialize($name)
	{
		if (!isset($this->config[$name])) {
			return;
		}

		foreach ($this->config[$name] as $index => $config) {
			$map = $this->config_map[$name][$index];

			$reflection = Reflection::createFromCallable($config);

			if (($params = $reflection->getParameters()) !== []) {
				$params = $this->resolve($params, $map);
			}

			$value = $config(...$params);

			if ($value !== null) {
				$this->values[$name] = $value;
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
	 * @param callable|mixed|mixed[]|null $func_or_map_or_type creation function or class-name, or, if the first
	 *                                                         argument is a class-name, a map of constructor arguments
	 * @param mixed|mixed[]               $map                 mixed list/map of parameter values (and/or boxed values)
	 *
	 * @return void
	 *
	 * @throws ContainerException
	 */
	public function register($name, $func_or_map_or_type = null, $map = [])
	{
		if (isset($this->active[$name])) {
			throw new ContainerException("Attempted override of existing component: {$name}");
		}

		if ($func_or_map_or_type instanceof \Closure) {
			$func = $func_or_map_or_type;
		} elseif (is_callable($func_or_map_or_type)) {
			// second argument is a creation function
			$func = \Closure::fromCallable($func_or_map_or_type);
		} elseif (is_string($func_or_map_or_type)) {
			// second argument is a class-name
			$func = $func_or_map_or_type;
		} elseif (is_array($func_or_map_or_type)) {
			$func = $name;
			$map = $func_or_map_or_type;
		} elseif (null === $func_or_map_or_type) {
			// first argument is both the component and class-name
			$func = $name;
			$map = [];
		} else {
			throw new ContainerException('Unexpected argument type for $func_or_map_or_type: ' . gettype($func_or_map_or_type));
		}

		$this->factory[$name] = $func;
		$this->factory_map[$name] = $map;

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
	 * @throws ContainerException
	 */
	public function set($name, $value)
	{
		if (isset($this->active[$name])) {
			throw new ContainerException("Attempted override of existing component: {$name}");
		}

		$this->values[$name] = $value;

		unset(
			$this->factory[$name],
			$this->factory_map[$name],
			$this->alias[$name]
		);
	}

	/**
	 * Register a component as an alias of another registered component.
	 *
	 * @param string $alias new component name
	 * @param string $name  referenced existing component name
	 *
	 * @throws InvalidArgumentException
	 */
	public function alias(string $alias, string $name): void
	{
		if (isset($this->values[$alias]) || isset($this->factory[$alias]) || array_key_exists($alias, $this->values)) {
			throw new InvalidArgumentException("Already defined in container: $alias");
		}

		if ($alias === $name) {
			throw new InvalidArgumentException('Alias cannot be the same as the original name');
		}

		$this->alias[$alias] = $name;
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
	 * @param string|callable        $name_or_func component name
	 *                                             (or callable, if name is left out)
	 * @param callable|mixed|mixed[] $func_or_map  `function (Type $component, ...) : void`
	 *                                             (or parameter values, if name is left out)
	 * @param mixed|mixed[]          $map          mixed list/map of parameter values and/or boxed values
	 *                                             (or unused, if name is left out)
	 *
	 * @return void
	 *
	 * @throws ContainerException
	 */
	public function configure($name_or_func, $func_or_map = null, $map = [])
	{
		if (is_callable($name_or_func)) {
			$func = $name_or_func;
			$map = $func_or_map ?: [];

			// no component name supplied, infer it from the closure:

			if ($func instanceof \Closure) {
				$param = new \ReflectionParameter($func, 0); // shortcut reflection for closures (as an optimization)
			} else {
				[$param] = Reflection::createFromCallable($func)->getParameters();
			}

			$name = Reflection::getParameterType($param); // infer component name from type-hint

			if ($name === null) {
				throw new ContainerException('No component-name or type-hint specified');
			}
		} else {
			$name = $name_or_func;
			$func = $func_or_map;

			if ($map === [] || !array_key_exists(0, $map)) {
				$map[0] = $this->ref($name);
			}
		}

		$this->config[$name][] = $func;
		$this->config_map[$name][] = $map;
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
	 * @return \Closure component reference
	 */
	public function ref($name)
	{
		if (isset($this->active[$name])) {
			return $this->values[$name];
		}

		return function () use ($name) {
			return $this->get($name);
		};
	}

	public function delete($name)
	{
		unset(
			$this->values[$name],
			$this->factory[$name],
			$this->factory_map[$name],
			$this->alias[$name]
		);
	}

	public function __call($name, $arguments)
	{
		return $this->get($name);
	}
}
