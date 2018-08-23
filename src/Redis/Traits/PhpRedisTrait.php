<?php

namespace Hail\Redis\Traits;


use Hail\Redis\Exception\RedisException;
use Hail\Redis\Helpers;

trait PhpRedisTrait
{
    /**
     * Socket connection to the Redis server or Redis library instance
     *
     * @var \Redis|\RedisCluster
     */
    protected $redis;

    /**
     * Redis instance in multi-mode
     *
     * @var \Redis|\RedisCluster
     */
    protected $redisMulti;

    /**
     * Aliases for backwards compatibility with phpredis
     *
     * @var array
     */
    protected static $wrapperMethods = ['delete' => 'del', 'getkeys' => 'keys', 'sremove' => 'srem'];

    /**
     * @var int
     */
    protected $requests = 0;

    protected static $typeMap = [
        \Redis::REDIS_NOT_FOUND => 'none',
        \Redis::REDIS_STRING => 'string',
        \Redis::REDIS_SET => 'set',
        \Redis::REDIS_LIST => 'list',
        \Redis::REDIS_ZSET => 'zset',
        \Redis::REDIS_HASH => 'hash',
    ];

    protected static $skipMap = [
        'get' => true,
        'set' => true,
        'hget' => true,
        'hset' => true,
        'setex' => true,
        'mset' => true,
        'msetnx' => true,
        'hmset' => true,
        'hmget' => true,
        'del' => true,
        'zrangebyscore' => true,
        'zrevrangebyscore' => true,
        'subscribe' => true,
        'psubscribe' => true,
        'scan' => true,
        'sscan' => true,
        'hscan' => true,
        'zscan' => true,
    ];

    public function close($force = false)
    {
        $result = true;
        if ($this->redis && ($force || !$this->persistent)) {
            try {
                $this->redis->close();
            } catch (\Exception $e) {
                // Ignore exceptions on close
            }

            $this->connected = false;
            $this->redisMulti = null;
        }

        return $result;
    }

    protected function normalize($name, $args)
    {
        $name = \strtolower($name);

        // Use aliases to be compatible with phpredis wrapper
        $name = self::$wrapperMethods[$name] ?? $name;

        // Tweak arguments
        if (!isset(self::$skipMap[$name])) {
            switch ($name) {
                case 'zrange':
                case 'zrevrange':
                    if (isset($args[3]) && \is_array($args[3]))
                    {
                        $cArgs = $args[3];
                        $args[3] = !empty($cArgs['withscores']);
                    }
                    $args = Helpers::flattenArguments($args);
                    break;
                case 'zunionstore':
                    $cArgs = [
                        $args[0], // destination
                        $args[1], // keys
                    ];
                    if (isset($args[2], $args[2]['weights'])) {
                        $cArgs[] = (array) $args[2]['weights'];
                    } else {
                        $cArgs[] = null;
                    }
                    if (isset($args[2], $args[2]['aggregate'])) {
                        $cArgs[] = \strtoupper($args[2]['aggregate']);
                    }
                    $args = $cArgs;
                    break;
                case 'mget':
                    if (isset($args[0]) && !\is_array($args[0])) {
                        $args = [$args];
                    }
                    break;
                case 'lrem':
                    $args = [$args[0], $args[2], $args[1]];
                    break;
                case 'eval':
                case 'evalsha':
                    $cKeys = $cArgs = [];
                    if (isset($args[1])) {
                        if (\is_array($args[1])) {
                            $cKeys = $args[1];
                        } elseif (\is_string($args[1])) {
                            $cKeys = [$args[1]];
                        }
                    }
                    if (isset($args[2])) {
                        if (\is_array($args[2])) {
                            $cArgs = $args[2];
                        } elseif (\is_string($args[2])) {
                            $cArgs = [$args[2]];
                        }
                    }
                    $args = [$args[0], \array_merge($cKeys, $cArgs), \count($cKeys)];
                    break;
                case 'pipline':
                case 'multi':
                    if ($this->redisMulti !== null) {
                        return $this;
                    }
                    break;
                case 'subscribe':
                case 'psubscribe':
                    $args[0] = (array) $args[0];
                default:
                    // Flatten arguments
                    $args = Helpers::flattenArguments($args);
            }
        }

        return [$name, $args];
    }

    /**
     * @param $name
     * @param $args
     *
     * @return $this
     * @throws \RedisException
     */
    protected function executeMethod($name, $args)
    {
        // Send request, retry one time when using persistent connections on the first request only
        if ($this->persistent && $this->requests === 0) {
            $this->requests = 1;
            try {
                $response = $this->redis->$name(...$args);
            } catch (\RedisException $e) {
                if ($e->getMessage() === 'read error on connection') {
                    $this->close(true);
                    $this->connect();
                    $response = $this->redis->$name(...$args);
                } else {
                    throw $e;
                }
            }
        } else {
            $response = $this->redis->$name(...$args);
        }

        // Proxy pipeline mode to the phpredis library
        if ($name === 'pipeline' || $name === 'multi') {
            $this->redisMulti = $response;

            return $this;
        }

        if ($name === 'exec' || $name === 'discard') {
            $this->redisMulti = null;

            return $response;
        }

        if ($this->redisMulti !== null) { // Multi and pipeline return self for chaining
            return $this;
        }

        return $response;
    }

    /**
     * @param $name
     * @param $response
     *
     * @return null
     * @throws RedisException
     */
    protected function parseResponse($name, $response)
    {
        if ($response instanceof static) {
            return $response;
        }

        #echo "> $name : ".substr(print_r($response, TRUE),0,100)."\n";

        // change return values where it is too difficult to minim in standalone mode
        switch ($name) {
            case 'type':
                $response = self::$typeMap[$response];
                break;

            // Handle scripting errors
            case 'eval':
            case 'evalsha':
            case 'script':
                $error = $this->redis->getLastError();
                $this->redis->clearLastError();
                if ($error && \strpos($error, 'NOSCRIPT') === 0) {
                    $response = null;
                } elseif ($error) {
                    throw new RedisException($error);
                }
                break;
            case 'exists':
                // smooth over phpredis-v4 vs earlier difference to match documented credis return results
                $response = (int) $response;
                break;
            default:
                $error = $this->redis->getLastError();
                $this->redis->clearLastError();
                if ($error) {
                    throw new RedisException($error);
                }
                break;
        }

        return $response;
    }
}