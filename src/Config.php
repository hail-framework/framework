<?php

namespace Hail;

\defined('OPCACHE_INVALIDATE') || \define('OPCACHE_INVALIDATE', \function_exists('\opcache_invalidate'));

use Hail\Optimize\OptimizeTrait;
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
    protected $items = [];

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @var string
     */
    protected $folder;
    protected $cacheFolder;

    public function __construct(string $folder = null, string $cacheFolder = null)
    {
        $this->folder = $folder ?? \base_path('config');
        $this->cacheFolder = $cacheFolder ?? \runtime_path('config');
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        \Arrays::set($this->items, $key, $value);
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
                    $found = \Arrays::get($found, $split[1]);
                }
            }
        } else {
            $found = \Arrays::get($this->items, $key);
        }

        return $this->cache[$key] = $found;
    }

    public function delete($key)
    {
        \Arrays::delete($this->items, $key);
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
    protected function load(string $space): ?array
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
    protected function loadFile(?string $file)
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
     * @param $file
     *
     * @return array|mixed
     */
    protected function loadYaml($file, $ext)
    {
        $filename = \basename($file);
        $dir = $this->cacheFolder;

        $cache = $dir . DIRECTORY_SEPARATOR . substr($filename, 0, -\strlen($ext)) . '.php';

        if (@\filemtime($cache) < \filemtime($file)) {
            $content = \Yaml::decodeFile($file);

            if (!\is_dir($dir) && !@\mkdir($dir, 0755) && !\is_dir($dir)) {
                throw new \RuntimeException('Temp directory permission denied');
            }

            \file_put_contents($cache, '<?php return ' . $this->parseArrayToCode($content) . ';');

            if (OPCACHE_INVALIDATE) {
                \opcache_invalidate($cache, true);
            }
        }

        return include $cache;
    }

    protected function parseArrayToCode(array $array, $level = 0): string
    {
        $pad = '';
        if ($level > 0) {
            $pad = \str_repeat("\t", $level);
        }

        $isAssoc = \Arrays::isAssoc($array);

        $ret = '[' . "\n";
        foreach ($array as $k => $v) {
            $ret .= $pad . "\t";
            if ($isAssoc) {
                $ret .= \var_export($k, true) . ' => ';
            }

            if (\is_array($v)) {
                $ret .= $this->parseArrayToCode($v, $level + 1);
            } else {
                $ret .= $this->parseValue($v);
            }

            $ret .= ',' . "\n";
        }

        return $ret . $pad . ']';
    }

    /**
     * Parse
     *
     * @param $value
     *
     * @return mixed
     */
    protected function parseValue($value)
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
                return $value;
            }

            if (!isset($matches[2])) {
                return $function . '()';
            }

            $args = \explode(',', $matches[2]);
            foreach ($args as &$a) {
                $a = $this->parseConstant(\trim($a));
            }

            return $function . '(' . \implode(', ', $args) . ')';
        }

        return $this->parseConstant($value);
    }

    protected function parseConstant(string $value)
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

    protected function getFile(string $space): ?string
    {
        foreach (['.php', '.yml', '.yaml'] as $ext) {
            $real = \absolute_path($this->folder, $space . $ext);
            if (\file_exists($real)) {
                return $real;
            }
        }

        return null;
    }

    public function getMTime(string $key): ?int
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