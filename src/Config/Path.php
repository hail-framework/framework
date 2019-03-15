<?php

namespace Hail\Config;

/**
 * Class Path
 *
 * @package Hail\Util
 *
 * @method string root(string ...$paths)
 * @method string hail(string ...$paths)
 * @method string app(string ...$paths)
 * @method string storage(string ...$paths)
 * @method string runtime(string ...$paths)
 */
class Path
{
    private $base = [];

    public function __construct(array $bases = [])
    {
        foreach ($bases as $k => $v) {
            $this->base($k, $v);
        }
    }

    /**
     * @param string $key
     * @param string|null  $path
     *
     * @return string|null
     */
    public function base(string $key, string $path = null): ?string
    {
        if ($key[0] === '@') {
            $key = \substr($key, 1);
        }

        if ($path === null) {
            return $this->base[$key] ?? null;
        }

        if (($absolute = \realpath($path)) === false) {
            throw new \InvalidArgumentException('Path not exists: ' . $path);
        }

        $this->base[$key] = $absolute;

        return null;
    }

    public function absolute(string $root, string ...$paths): string
    {
        if ($root[0] === '@') {
            $absoluteRoot = $this->base($root);
        } else {
            $root = \rtrim(
                \str_replace('\\', '/', $root),
                '/'
            );

            if (($absoluteRoot = \realpath($root)) === false) {
                throw new \InvalidArgumentException('ROOT path not exists: ' . $root);
            }
        }

        if ($paths === []) {
            return $absoluteRoot;
        }

        if (!isset($paths[1])) {
            $path = $paths[0];
        } else {
            $path = \implode('/', $paths);
        }

        $path = $absoluteRoot . '/' . \trim(
                \str_replace('\\', '/', $path),
                '/'
            );

        if (($absolutePath = \realpath($path)) === false) {
            $parts = \explode('/', $path);
            $absolutes = [];
            foreach ($parts as $part) {
                if ('.' === $part || '' === $part) {
                    continue;
                }

                if ('..' === $part) {
                    \array_pop($absolutes);
                } else {
                    $absolutes[] = $part;
                }
            }

            $absolutePath = implode(DIRECTORY_SEPARATOR, $absolutes);
            if ($absoluteRoot[0] === '/' && $absolutePath[0] !== '/') {
                $absolutePath = '/' . $absolutePath;
            }

            if (\strpos($absolutePath, $absoluteRoot) !== 0) {
                throw new \InvalidArgumentException('Path can not higher than ROOT.');
            }
        }

        return $absolutePath;
    }

    /**
     * @param string   $name
     * @param string[] $arguments
     *
     * @return string
     */
    public function __call(string $name, array $arguments): string
    {
        if (!isset($this->base[$name])) {
            throw new \RuntimeException("Base path not defined '$name'");
        }

        return $this->absolute($this->base[$name], ...$arguments);
    }
}