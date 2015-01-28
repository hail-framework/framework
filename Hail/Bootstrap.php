<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/1/26 0026
 * Time: 18:46
 */

namespace Hail;

/**
 * Init Class
 *
 */
class Bootstrap
{
    public function __construct($config)
    {
        if (!empty($config['path'])) {
            $this->paths($config['path']);
        }
    }

    protected function paths($paths)
    {
        if (empty($paths['app'])) {
            $paths['app'] = __DIR__ . '../app';
        }

        foreach($paths as &$v) {
            $v = rtrim($v, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        define(AP, $paths['app']);
    }
}