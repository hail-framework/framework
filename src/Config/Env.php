<?php

namespace Hail\Config;

/**
 * This is the dotenv class.
 *
 * It's responsible for loading a `.env` file in the given directory and
 * setting the environment vars.
 */
class Env
{
    public const FILE = '.env';

    private $loaded = [];

    /**
     * Are we immutable?
     *
     * @var bool
     */
    protected $immutable = true;

    /**
     * The list of environment variables declared inside the 'env' file.
     *
     * @var array
     */
    protected $names = [];

    public function __construct(array $files)
    {
        foreach ($files as $v) {
            $this->load($v);
        }
    }

    /**
     * Set immutable value.
     *
     * @param bool $immutable
     * @return self
     */
    public function setImmutable(bool $immutable = false): self
    {
        $this->immutable = $immutable;

        return $this;
    }

    /**
     * Get immutable value.
     *
     * @return bool
     */
    public function isImmutable(): bool
    {
        return $this->immutable;
    }

    /**
     * Get the list of environment variables declared inside the 'env' file.
     *
     * @return array
     */
    public function getNames(): array
    {
        return $this->names;
    }

    /**
     * Load `.env` file
     *
     * @param string $file
     * @return self
     */
    public function load(string $file): self
    {
        if (!\is_file($file) || !\is_readable($file)) {
            return $this;
        }

        $array = \parse_ini_file($file, false, INI_SCANNER_RAW);

        foreach ($array as $name => $value) {
            $this->set($name, $value);
        }

        $this->loaded[] = $file;

        return $this;
    }

    /**
     * Search the different places for environment variables and return first value found.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function get(string $name): ?string
    {
        if (isset($_ENV[$name])) {
            return $_ENV[$name];
        }

        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        }

        $value = \getenv($name);

        return $value === false ? null : $value; // switch getenv default to null
    }

    /**
     * Set an environment variable.
     *
     * @param string $name
     * @param string $value
     *
     * @return void
     */
    protected function set(string $name, string $value): void
    {
        $name = \trim($name);

        if (
            (isset($name[0]) && $name[0] === '#') ||
            ($this->immutable && $this->get($name) !== null)
        ) {
            return;
        }

        $this->names[] = $name;
        $value = \trim($value);

        // If PHP is running as an Apache module and an existing
        // Apache environment variable exists, overwrite it
        if (
            \function_exists('\\apache_setenv') &&
            \function_exists('\\apache_getenv') &&
            \apache_getenv($name) !== false
        ) {
            \apache_setenv($name, $value);
        }

        if (\function_exists('\\putenv')) {
            \putenv("$name=$value");
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    /**
     * Clear an environment variable.
     *
     * @param string $name
     *
     * @return self
     */
    public function clear($name): self
    {
        if ($this->immutable) {
            return $this;
        }

        if (\function_exists('\\putenv')) {
            \putenv($name);
        }

        unset($_ENV[$name], $_SERVER[$name]);

        return $this;
    }

    public function reset(): self
    {
        if ($this->immutable) {
            return $this;
        }

        $old = $this->names;
        $loaded = \array_unique($this->loaded);
        $this->loaded = $this->names = [];
        foreach ($loaded as $file) {
            $this->load($file);
        }

        $diff = \array_diff($old, $this->names);
        foreach ($diff as $v) {
            $this->clear($v);
        }

        return $this;
    }
}