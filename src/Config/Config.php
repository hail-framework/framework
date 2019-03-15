<?php

namespace Hail\Config;

\defined('OPCACHE_INVALIDATE') || \define('OPCACHE_INVALIDATE', \function_exists('\opcache_invalidate'));

use Hail\Optimize\OptimizeTrait;
use Hail\Serialize\Yaml;
use Hail\Util\Arrays;
use Hail\Util\ArrayTrait;

/**
 * Class Php
 *
 * @package Hail\Config
 */
class Config implements \ArrayAccess
{
    use OptimizeTrait;
    use ArrayTrait;

    /**
     * All of the configuration items.
     *
     * @var array
     */
    private $items = [];

    /**
     * @var array
     */
    private $cache = [];

    /**
     * @var string
     */
    private $folder;
    private $cacheFolder;
    private $arrays;
    private $yaml;

    public function __construct(string $folder, string $cacheFolder = null)
    {
        if (!\is_dir($err = $folder) || ($cacheFolder && !\is_dir($err = $cacheFolder))) {
            throw new \InvalidArgumentException("Folder not exists '$err'");
        }

        $this->folder = $folder;
        $this->cacheFolder = $cacheFolder;

        $this->arrays = Arrays::getInstance();
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function set(string $key, $value): void
    {
        $this->arrays->set($this->items, $key, $value);
        $this->cache = [];
    }

    /**
     * Get the specified configuration value.
     *
     * @param  string $key
     *
     * @return mixed
     */
    public function get(string $key)
    {
        if ($key === '' || $key === '.') {
            return null;
        }

        if (isset($this->items[$key])) {
            return $this->items[$key];
        }

        if (\array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        [$space] = $split = \explode('.', $key, 2);

        if (!isset($this->items[$space])) {
            if (($found = $this->load($space)) !== null) {
                $this->items[$space] = $found;

                if (isset($split[1])) {
                    $found = $this->arrays->get($found, $split[1]);
                }
            }
        } else {
            $found = $this->arrays->get($this->items, $key);
        }

        return $this->cache[$key] = $found;
    }

    public function delete(string $key): void
    {
        $this->arrays->delete($this->items, $key);
        $this->cache = [];
    }

    /**
     * Read config array from cache or file
     * Extensions order: php > yml > yaml
     *
     * @param string $space
     *
     * @return array|null
     */
    private function load(string $space): ?array
    {
        $file = $this->getFile($space);

        if ($file === null) {
            return null;
        }

        if (($config = static::optimizeGet($space, $file)) !== false) {
            return (array) $config;
        }

        $config = $this->loadFile($file);

        static::optimizeSet($space, $config, $file);

        return $config;
    }

    /**
     * @param string $file
     *
     * @return array|mixed
     */
    private function loadFile(?string $file)
    {
        if ($file === null) {
            return [];
        }

        $ext = \strrchr($file, '.');
        if ($ext === '.yml' || $ext === '.yaml') {
            return $this->loadYaml($file, $ext);
        }

        return require $file;
    }

    /**
     * Parse a YAML file or load it from the cache
     *
     * @param string $file
     * @param string $ext
     *
     * @return array|mixed
     */
    private function loadYaml(string $file, string $ext)
    {
        if ($this->cacheFolder === null) {
            $content = $this->decodeYaml($file);

            return $this->parseArray($content);
        }

        $dir = $this->cacheFolder;
        $filename = \basename($file);
        $cache = $dir . DIRECTORY_SEPARATOR . substr($filename, 0, -\strlen($ext)) . '.php';

        if (@\filemtime($cache) < \filemtime($file)) {
            $content = $this->decodeYaml($file);

            if (!\is_dir($dir) && !@\mkdir($dir, 0755) && !\is_dir($dir)) {
                throw new \RuntimeException('Temp directory permission denied');
            }

            \file_put_contents($cache, '<?php return ' . $this->parseArrayCode($content) . ';');

            if (OPCACHE_INVALIDATE) {
                \opcache_invalidate($cache, true);
            }
        }

        return include $cache;
    }

    private function decodeYaml(string $file): array
    {
        if ($this->yaml === null) {
            $this->yaml = Yaml::getInstance();
        }

        return $this->yaml->decodeFile($file);
    }

    private function parseArray(array $array): array
    {
        foreach ($array as &$v) {
            if (\is_array($v)) {
                $v = $this->parseArray($v);
            } else {
                $v = $this->parseValue($v);
            }
        }

        return $array;
    }

    private function parseArrayCode(array $array, int $level = 0): string
    {
        $pad = '';
        if ($level > 0) {
            $pad = \str_repeat("\t", $level);
        }

        $isAssoc = $this->arrays->isAssoc($array);

        $ret = '[' . "\n";
        foreach ($array as $k => $v) {
            $ret .= $pad . "\t";
            if ($isAssoc) {
                $ret .= \var_export($k, true) . ' => ';
            }

            if (\is_array($v)) {
                $ret .= $this->parseArrayCode($v, $level + 1);
            } else {
                $ret .= $this->parseValueCode($v);
            }

            $ret .= ',' . "\n";
        }

        return $ret . $pad . ']';
    }

    private function parseValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        if (\preg_match('/%([a-zA-Z0-9_:\\\]+)(?::(.*))?%/', $value, $matches)) {
            $function = $matches[1];

            if (!\function_exists($function)) {
                return $value;
            }

            if (!isset($matches[2])) {
                return $function();
            }

            $args = \explode(',', $matches[2]);
            foreach ($args as &$a) {
                $a = $this->parseConstant(\trim($a));
            }

            return $function(...$args);
        }

        return $this->parseConstant($value);
    }

    /**
     * Parse
     *
     * @param mixed $value
     *
     * @return string
     */
    private function parseValueCode($value): string
    {
        if ($value instanceof \DateTime) {
            return 'new \\DateTime(' . \var_export($value->format('c'), true) . ')';
        }

        if (!\is_string($value)) {
            return \var_export($value, true);
        }

        if (\preg_match('/%([a-zA-Z0-9_:\\\]+)(?::(.*))?%/', $value, $matches)) {
            $function = $matches[1];

            if (!\function_exists($function)) {
                return \var_export($value, true);
            }

            if (!isset($matches[2])) {
                return $function . '()';
            }

            $args = \explode(',', $matches[2]);
            foreach ($args as &$a) {
                $a = $this->parseConstantCode(\trim($a));
            }

            return $function . '(' . \implode(', ', $args) . ')';
        }

        return $this->parseConstantCode($value);
    }

    private function parseConstant(string $value): string
    {
        return \preg_replace_callback('/\${([a-zA-Z0-9_:\\\]+)}/', static function ($matches) {
            return \defined($matches[1]) ? \constant($matches[1]) : $matches[0];
        }, $value);
    }

    private function parseConstantCode(string $value): string
    {
        $value = \var_export($value, true);

        \preg_match_all('/\${([a-zA-Z0-9_:\\\]+)}/', $value, $matches);

        if (!empty($matches[0])) {
            $replace = [];
            foreach ($matches[0] as $k => $v) {
                $replace[$v] = '\' . ' . \str_replace('\\\\', '\\', $matches[1][$k]) . ' . \'';
            }

            if ($replace !== []) {
                $value = \strtr($value, $replace);

                $start = 0;
                if (\strpos($value, "'' . ") === 0) {
                    $start = 5;
                }

                $end = null;
                if (\strrpos($value, " . ''", 5) > 0) {
                    $end = -5;
                }

                if ($end !== null) {
                    $value = \substr($value, $start, $end);
                } elseif ($start !== 0) {
                    $value = \substr($value, $start);
                }
            }
        }

        return $value;
    }

    private function getFile(string $space): ?string
    {
        foreach (['.php', '.yml', '.yaml'] as $ext) {
            $real = \absolute_path($this->folder, $space . $ext);
            if (\file_exists($real)) {
                return $real;
            }
        }

        return null;
    }

    public function modifyTime(string $key): ?int
    {
        $file = $this->getFile(
            \explode('.', $key, 2)[0]
        );

        if ($file === null) {
            return null;
        }

        return \filemtime($file);
    }
}