<?php

namespace Hail;

use Hail\Util\{
    Arrays, ArrayTrait, OptimizeTrait, Yaml
};

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

    public function __construct(string $folder)
    {
        $this->folder = $folder;
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        Arrays::set($this->items, $key, $value);
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
                    $found = Arrays::get($found, $split[1]);
                }
            }
        } else {
            $found = Arrays::get($this->items, $key);
        }

        return $this->cache[$key] = $found;
    }

    public function delete($key)
    {
        Arrays::delete($this->items, $key);
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
        $files = $this->getFile($space);

        if ($files === []) {
            return null;
        }

        if (($config = static::optimizeGet($space, $files)) !== false) {
            return (array) $config;
        }

        $config = [];
        foreach ($files as $v) {
            $config += static::loadFromFile($v);
        }

        static::optimizeSet($space, $config, $files);

        return $config;
    }

    /**
     * @param string $file from static::foundFile, already make sure file is exists
     *
     * @return array|mixed
     */
    protected static function loadFromFile(?string $file)
    {
        if ($file === null) {
            return [];
        }

        $ext = \strrchr($file, '.');
        if ($ext === '.yml' || $ext === '.yaml') {
            return static::loadYaml($file, $ext);
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
    protected static function loadYaml($file, $ext)
    {
        $filename = \basename($file);
        $dir = \runtime_path('yaml');

        $cache = $dir . DIRECTORY_SEPARATOR . substr($filename, 0, -\strlen($ext)) . '.php';

        if (@\filemtime($cache) < \filemtime($file)) {
            $content = Yaml::parseFile($file);

            if (!\is_dir($dir) && !@\mkdir($dir, 0755) && !\is_dir($dir)) {
                throw new \RuntimeException('Temp directory permission denied');
            }

            \file_put_contents($cache, '<?php return ' . static::parseArrayToCode($content) . ';');

            if (\function_exists('\opcache_invalidate')) {
                \opcache_invalidate($cache, true);
            }
        }

        return include $cache;
    }

    protected static function parseArrayToCode(array $array, $level = 0): string
    {
        $pad = '';
        if ($level > 0) {
            $pad = \str_repeat("\t", $level);
        }

        $isAssoc = Arrays::isAssoc($array);

        $ret = '[' . "\n";
        foreach ($array as $k => $v) {
            $ret .= $pad . "\t";
            if ($isAssoc) {
                $ret .= \var_export($k, true) . ' => ';
            }

            if (\is_array($v)) {
                $ret .= static::parseArrayToCode($v, $level + 1);
            } else {
                $ret .= static::parseValue($v);
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
    protected static function parseValue($value)
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
                $a = static::parseConstant(\trim($a));
            }

            return $function . '(' . \implode(', ', $args) . ')';
        }

        return static::parseConstant($value);
    }

    protected static function parseConstant(string $value)
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
            $real = \absolute_path($this->folder, 'config', $space . $ext);
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