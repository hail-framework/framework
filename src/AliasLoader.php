<?php
/**
 * @from https://github.com/laravel/framework/blob/5.4/src/Illuminate/Foundation/AliasLoader.php
 * Copyright (c) <Taylor Otwell> Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail;

\defined('OPCACHE_INVALIDATE') || \define('OPCACHE_INVALIDATE', \function_exists('\opcache_invalidate'));

/**
 * Class AliasLoader
 *
 * @package Hail
 */
class AliasLoader
{
    /**
     * The array of class aliases.
     *
     * @var array
     */
    protected $aliases;

    /**
     * Indicates if a loader has been registered.
     *
     * @var bool
     */
    protected $registered = false;

    /**
     * The namespace for all real-time facades.
     *
     * @var string
     */
    private const FACADE_NAMESPACE = 'Facade\\';

    /**
     * Create a new AliasLoader instance.
     *
     * @param  array $aliases
     */
    public function __construct(array $aliases)
    {
        $this->aliases = $aliases;
    }

    /**
     * Load a class alias if it is registered.
     *
     * @param  string $alias
     *
     * @return bool | null
     */
    public function load(string $alias): ?bool
    {
        if (\strpos($alias, self::FACADE_NAMESPACE) === 0) {
            return $this->loadFacade($alias);
        }

        if (isset($this->aliases[$alias])) {
            return \class_alias($this->aliases[$alias], $alias);
        }

        return null;
    }

    /**
     * Load a real-time facade for the given alias.
     *
     * @param  string $alias
     *
     * @return bool
     */
    protected function loadFacade(string $alias): bool
    {
        require $this->ensureFacadeExists($alias);

        return true;
    }

    /**
     * Ensure that the given alias has an existing real-time facade class.
     *
     * @param  string $alias
     *
     * @return string
     * @throws \RuntimeException
     */
    protected function ensureFacadeExists(string $alias): string
    {
        if (\file_exists($path = \runtime_path('facade', $alias . '.php'))) {
            return $path;
        }

        if (!\is_dir($dir = \dirname($path)) && !@\mkdir($dir) && !\is_dir($dir)) {
            throw new \RuntimeException('Temp directory permission denied');
        }

        \file_put_contents($path, $this->formatFacadeCode($alias));

        if (OPCACHE_INVALIDATE) {
            \opcache_invalidate($path, true);
        }

        return $path;
    }

    /**
     * Format the facade stub with the proper namespace and class.
     *
     * @param  string $alias
     *
     * @return string
     */
    protected function formatFacadeCode(string $alias): string
    {
        static $len;
        if ($len === null) {
            $len = \strlen(self::FACADE_NAMESPACE);
        }

        $path = \str_replace('\\', '/', $alias);

        $namespace = \str_replace('/', '\\', \dirname($path));
        $class = \basename($path);
        $target = '\\' . \substr($alias, $len);

        $code = <<<STUB
<?php
namespace $namespace;

use Hail\Facade\FacadeDynamic;

/**
 * @see $target
 */
class $class extends FacadeDynamic
{
    protected static \$name = '$target';
}
STUB;

        return $code;
    }

    /**
     * Add an alias to the loader.
     *
     * @param  string $class
     * @param  string $alias
     *
     * @return void
     */
    public function alias(string $class, string $alias): void
    {
        $this->aliases[$class] = $alias;
    }

    /**
     * Get the registered alias.
     *
     * @param string $class
     *
     * @return string
     */
    public function getAlias(string $class): string
    {
        return $this->aliases[$class] ?? $class;
    }

    /**
     * Get the registered aliases.
     *
     * @return array
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Set the registered aliases.
     *
     * @param  array $aliases
     *
     * @return void
     */
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Prepend the loader to the auto-loader stack.
     *
     * @return void
     */
    public function register(): void
    {
        if (!$this->registered) {
            \spl_autoload_register([$this, 'load'], true, true);
            $this->registered = true;
        }
    }
}
