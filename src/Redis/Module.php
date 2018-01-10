<?php
/**
 * Credis_Module
 *
 * Implements Redis Modules support. see http://redismodules.com
 *
 * @author Igor Veremchuk <igor.veremchuk@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package Credis_Module
 */

namespace Hail\Redis;


use Hail\Redis\Exception\RedisException;

class Module
{
    public const MODULE_COUNTING_BLOOM_FILTER = 'CBF';

    /** @var RedisInterface */
    protected $client;

    /** @var  string */
    protected $moduleName;

    /**
     * Module constructor.
     *
     * @param array       $config
     * @param string|null $module
     */
    public function __construct(array $config, string $module = null)
    {
        // Redis Modules command not currently supported by phpredis
        $this->client = new Client\Native($config);

        if ($module !== null) {
            $this->setModule($module);
        }
    }

    /**
     * Clean up client on destruct
     */
    public function __destruct()
    {
        $this->client->close();
    }

    /**
     * @param $moduleName
     * @return $this
     */
    public function setModule($moduleName)
    {
        $this->moduleName = (string) $moduleName;

        return $this;
    }

    /**
     * @param $name
     * @param $args
     *
     * @return $this|array|bool|mixed|null|string
     * @throws RedisException
     */
    public function __call($name, $args)
    {
        if ($this->moduleName === null) {
            throw new \LogicException('Module must be set.');
        }

        $name = "{$this->moduleName}.{$name}";
        $args = Helpers::flattenArguments($args);

        return $this->client->execute($name, $args);
    }
}