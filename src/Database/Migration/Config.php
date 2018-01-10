<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Config
 */

namespace Hail\Database\Migration;


/**
 * Phinx configuration class.
 *
 * @package Phinx
 * @author  Rob Morgan
 */
class Config
{
    /**
     * The value that identifies a version order by creation time.
     */
    public const VERSION_ORDER_CREATION_TIME = 'creation';

    /**
     * The value that identifies a version order by execution time.
     */
    public const VERSION_ORDER_EXECUTION_TIME = 'execution';

    /**
     * @var array
     */
    private $values;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $config)
    {
        $this->values = $config;
    }

    /**
     * Returns the configuration for each environment.
     *
     * This method returns <code>null</code> if no environments exist.
     *
     * @return array|null
     */
    public function getEnvironments()
    {
        if (isset($this->values['environments'])) {
            $environments = [];
            foreach ($this->values['environments'] as $key => $value) {
                if (is_array($value)) {
                    $environments[$key] = $value;
                }
            }

            return $environments;
        }

        return null;
    }

    /**
     * Returns the configuration for a given environment.
     *
     * This method returns <code>null</code> if the specified environment
     * doesn't exist.
     *
     * @param string $name
     * @return array|null
     */
    public function getEnvironment($name)
    {
        $environments = $this->getEnvironments();

        if (isset($environments[$name])) {
            if (isset($this->values['environments']['default_migration_table'])) {
                $environments[$name]['default_migration_table'] =
                    $this->values['environments']['default_migration_table'];
            }

            return $environments[$name];
        }

        return null;
    }

    /**
     * Does the specified environment exist in the configuration file?
     *
     * @param string $name Environment Name
     * @return bool
     */
    public function hasEnvironment($name)
    {
        return (null !== $this->getEnvironment($name));
    }

    /**
     * Gets the default environment name.
     *
     * @throws \RuntimeException
     * @return string
     */
    public function getDefaultEnvironment()
    {
        // if the user has configured a default database then use it,
        // providing it actually exists!
        if (isset($this->values['environments']['default_database'])) {
            if ($this->getEnvironment($this->values['environments']['default_database'])) {
                return $this->values['environments']['default_database'];
            }

            throw new \RuntimeException(sprintf(
                'The environment configuration for \'%s\' is missing',
                $this->values['environments']['default_database']
            ));
        }

        // else default to the first available one
        if (is_array($this->getEnvironments()) && count($this->getEnvironments()) > 0) {
            $names = array_keys($this->getEnvironments());

            return $names[0];
        }

        throw new \RuntimeException('Could not find a default environment');
    }

    /**
     * Get the aliased value from a supplied alias.
     *
     * @param string $alias
     *
     * @return string|null
     */
    public function getAlias($alias)
    {
        return !empty($this->values['aliases'][$alias]) ? $this->values['aliases'][$alias] : null;
    }

    /**
     * Gets the paths to search for migration files.
     *
     * @return string[]
     */
    public function getMigrationPaths()
    {
        if (!isset($this->values['paths']['migrations'])) {
            throw new \UnexpectedValueException('Migrations path missing from config file');
        }

        if (is_string($this->values['paths']['migrations'])) {
            $this->values['paths']['migrations'] = [$this->values['paths']['migrations']];
        }

        return $this->values['paths']['migrations'];
    }

    /**
     * Gets the base class name for migrations.
     *
     * @param bool $dropNamespace Return the base migration class name without the namespace.
     *
     * @return string
     */
    public function getMigrationBaseClassName($dropNamespace = true)
    {
        $className = $this->values['migration_base_class'] ?? AbstractMigration::class;

        if ($dropNamespace) {
            return substr(strrchr($className, '\\'), 1) ?: $className;
        }

        return $className;
    }

    /**
     * Gets the paths to search for seed files.
     *
     * @return string[]
     */
    public function getSeedPaths()
    {
        if (!isset($this->values['paths']['seeds'])) {
            throw new \UnexpectedValueException('Seeds path missing from config file');
        }

        if (is_string($this->values['paths']['seeds'])) {
            $this->values['paths']['seeds'] = [$this->values['paths']['seeds']];
        }

        return $this->values['paths']['seeds'];
    }

    /**
     * Get the template file name.
     *
     * @return string|false
     */
    public function getTemplateFile()
    {
        if (!isset($this->values['templates']['file'])) {
            return false;
        }

        return $this->values['templates']['file'];
    }

    /**
     * Get the template class name.
     *
     * @return string|false
     */
    public function getTemplateClass()
    {
        if (!isset($this->values['templates']['class'])) {
            return false;
        }

        return $this->values['templates']['class'];
    }

    /**
     * Get the version order.
     *
     * @return string
     */
    public function getVersionOrder()
    {
        if (!isset($this->values['version_order'])) {
            return self::VERSION_ORDER_CREATION_TIME;
        }

        return $this->values['version_order'];
    }

    /**
     * Is version order creation time?
     *
     * @return bool
     */
    public function isVersionOrderCreationTime()
    {
        $versionOrder = $this->getVersionOrder();

        return $versionOrder === self::VERSION_ORDER_CREATION_TIME;
    }

    /**
     * Search $needle in $haystack and return key associate with him.
     *
     * @param string $needle
     * @param array  $haystack
     * @return null|string
     */
    protected function searchNamespace($needle, $haystack)
    {
        $needle = realpath($needle);
        $haystack = array_map('realpath', $haystack);

        $key = array_search($needle, $haystack);

        return is_string($key) ? trim($key, '\\') : null;
    }

    /**
     * Get Migration Namespace associated with path.
     *
     * @param string $path
     * @return string|null
     */
    public function getMigrationNamespaceByPath($path)
    {
        $paths = $this->getMigrationPaths();

        return $this->searchNamespace($path, $paths);
    }

    /**
     * Get Seed Namespace associated with path.
     *
     * @param string $path
     * @return string|null
     */
    public function getSeedNamespaceByPath($path)
    {
        $paths = $this->getSeedPaths();

        return $this->searchNamespace($path, $paths);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($id, $value)
    {
        $this->values[$id] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($id)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        return $this->values[$id] instanceof \Closure ? $this->values[$id]($this) : $this->values[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($id)
    {
        return isset($this->values[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($id)
    {
        unset($this->values[$id]);
    }
}
