<?php

namespace Hail\Redis\Traits;

use Hail\SafeStorage\SafeStorageTrait;

trait RedisTrait
{
    use SafeStorageTrait;

    /**
     * Timeout for connecting to Redis server
     *
     * @var float
     */
    protected $timeout;

    /**
     * Timeout for reading response from Redis server
     *
     * @var float
     */
    protected $readTimeout;

    /**
     * Unique identifier for persistent connections
     *
     * @var string
     */
    protected $persistent;

    /**
     * @return string
     */
    public function getPersistence()
    {
        return $this->persistent;
    }

    /**
     * @param string $password
     *
     * @return bool
     */
    public function auth($password)
    {
        $response = $this->__call('auth', [$password]);
        $this->setPassword($password);

        return $response;
    }

    /**
     * @param array ...$pattern
     *
     * @return array
     */
    public function pUnsubscribe(...$pattern)
    {
        list($command, $channel, $subscribedChannels) = $this->__call('punsubscribe', $pattern);
        $this->subscribed = $subscribedChannels > 0;

        return [$command, $channel, $subscribedChannels];
    }

    /**
     * @param int    $Iterator
     * @param string $pattern
     * @param int    $count
     *
     * @return bool | array
     */
    public function scan(&$Iterator, $pattern = null, $count = null)
    {
        return $this->__call('scan', [&$Iterator, $pattern, $count]);
    }

    /**
     * @param int    $Iterator
     * @param string $field
     * @param string $pattern
     * @param int    $count
     *
     * @return bool | array
     */
    public function hscan(&$Iterator, $field, $pattern = null, $count = null)
    {
        return $this->__call('hscan', [$field, &$Iterator, $pattern, $count]);
    }

    /**
     * @param int    $Iterator
     * @param string $field
     * @param string $pattern
     * @param int    $Iterator
     *
     * @return bool | array
     */
    public function sscan(&$Iterator, $field, $pattern = null, $count = null)
    {
        return $this->__call('sscan', [$field, &$Iterator, $pattern, $count]);
    }

    /**
     * @param int    $Iterator
     * @param string $field
     * @param string $pattern
     * @param int    $Iterator
     *
     * @return bool | array
     */
    public function zscan(&$Iterator, $field, $pattern = null, $count = null)
    {
        return $this->__call('zscan', [$field, &$Iterator, $pattern, $count]);
    }

    /**
     * @param array ...$pattern
     *
     * @return array
     */
    public function unsubscribe(...$pattern)
    {
        list($command, $channel, $subscribedChannels) = $this->__call('unsubscribe', $pattern);
        $this->subscribed = $subscribedChannels > 0;

        return [$command, $channel, $subscribedChannels];
    }

    abstract public function __call($name, $args);
}
