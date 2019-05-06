<?php

namespace Hail\Util;

use Hail\Optimize\OptimizeTrait;

\defined('OPCACHE_INVALIDATE') || \define('OPCACHE_INVALIDATE', \function_exists('\opcache_invalidate'));

/**
 * Class Trie
 *
 * 使用 Trie 树的关键字过滤
 *
 * @package Hail\Util
 */
class Trie
{
    use OptimizeTrait;

    private const FILE = 'trie.php';

    private $tree = [];

    private $cache;

    public function __construct(string $path = null)
    {
        if (!$path) {
            return;
        }

        $file = \absolute_path($path, self::FILE);
        if (\is_file($path)) {
            $this->cache = $file;
            $this->tree = static::optimizeLoad($file);
        }
    }

    /**
     * @param array $tree
     *
     * @return self
     */
    public function set(array $tree): self
    {
        $this->tree = $tree;

        return $this;
    }

    /**
     * @return array
     */
    public function dump(): array
    {
        if ($this->cache) {
            \file_put_contents($this->cache,
                '<?php return ' . \var_export($this->tree, true)
            );

            if (OPCACHE_INVALIDATE) {
                \opcache_invalidate($this->cache, true);
            }

            self::optimizeSet('cache', $this->tree, $this->cache);
        }

        return $this->tree;
    }

    /**
     * @param array|string $add
     *
     * @return self
     */
    public function add($add): self
    {
        if (\is_array($add)) {
            \array_walk($array, [$this, 'addWord']);
        } else {
            $this->addWord($add);
        }

        return $this;
    }

    /**
     * @param string $word
     */
    private function addWord(string $word): void
    {
        if ($word === '') {
            return;
        }

        $tree = &$this->tree;
        $array = $this->split($word);

        foreach ($array as $char) {
            if (!isset($tree[$char])) {
                $tree[$char] = [];
            }

            $tree = &$tree[$char];
        }

        $tree['end'] = true;
    }

    /**
     * @param string $word
     *
     * @return bool
     */
    public function is(string $word): bool
    {
        if ($word === '') {
            return false;
        }

        $tree = &$this->tree;
        if ($tree === []) {
            return false;
        }

        $array = $this->split($word);

        foreach ($array as $char) {
            if (!isset($tree[$char])) {
                return false;
            }

            $tree = &$tree[$char];
        }

        return $tree['end'];
    }

    /**
     * @param string $text
     * @param int    $limit 0 is no limit
     *
     * @return array
     */
    public function search(string $text, int $limit = 0): array
    {
        $array = $this->split($text);
        $len = \count($array);

        $find = [];
        $position = $count = 0;
        $parent = false;
        $word = '';

        $tree = $this->tree;
        for ($i = 0; $i < $len; ++$i) {
            $char = $array[$i];

            if (isset($tree[$char])) {
                if (!$parent) {
                    $position = $i;
                    $parent = true;
                }

                $word .= $char;
                $tree = $tree[$char];

                if (isset($tree['end'])) {
                    $find[] = ['position' => $position, 'word' => $word];

                    if ($limit > 0 && ++$count === $limit) {
                        return $find;
                    }
                }
            } else {
                if ($parent) {
                    if (isset($tree['end'])) {
                        --$i;
                    } else {
                        $i = $position;
                    }
                    $parent = false;
                }

                $tree = $this->tree;
                $word = '';
            }
        }

        return $find;
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    public function check(string $text): bool
    {
        return $this->search($text, 1) !== [];
    }

    /**
     * @param string $text
     * @param string $mask
     *
     * @return string
     */
    public function replace(string $text, string $mask = '*'): string
    {
        $found = $this->search($text);

        $replace = [];
        foreach ($found as $v) {
            $replace[$v['word']] = $mask;
        }

        return \strtr($text, $replace);
    }

    /**
     * @param string $str
     *
     * @return array
     */
    private function split(string $str): array
    {
        return \preg_split('/(?<!^)(?!$)/u', $str);
    }
}
