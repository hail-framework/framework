<?php

namespace Hail\Cache\Simple;

use Hail\Util\Serialize;
use Hail\Redis\Client\AbstractClient;
use Hail\Redis\Exception\RedisException;
use Hail\Factory\Redis as RedisFactory;

/**
 * Redis cache provider.
 *
 * @author Feng Hao <flyinghail@msn.com>
 */
class Redis extends AbstractAdapter
{
    /**
     * @var AbstractClient|null
     */
    private $redis;

    /**
     * Redis constructor.
     *
     * @param array               $params
     * @param AbstractClient|null $redis
     *
     * @throws RedisException
     */
    public function __construct(array $params, AbstractClient $redis = null)
    {
        $this->redis = $redis ?? RedisFactory::client($params);

        parent::__construct($params);
    }

    /**
     * {@inheritdoc}
     */
    protected function doGet(string $key)
    {
        $value = $this->redis->get($key);
        if ($value === false) {
            return null;
        }

        return Serialize::decode($value);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSetMultiple(array $values, int $ttl = 0)
    {
        $success = true;

        if ($ttl > 0) {
            // Keys have lifetime, use SETEX for each of them
            foreach ($values as $key => $value) {
                if (!$this->redis->setEx($key, $ttl, Serialize::encode($value))) {
                    $success = false;
                }
            }
        } else {
            // No lifetime, use MSET
            $success = $this->redis->mSet(
                Serialize::encodeArray($values)
            );
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetMultiple(array $keys)
    {
        $fetchedItems = \array_combine($keys, $this->redis->mGet($keys));

        // Redis mget returns false for keys that do not exist. So we need to filter those out unless it's the real data.
        $foundItems = [];
        foreach ($fetchedItems as $key => $value) {
            if (false !== $value || $this->redis->exists($key)) {
                $foundItems[$key] = Serialize::decode($value);
            }
        }

        return $foundItems;
    }

    /**
     * {@inheritdoc}
     */
    protected function doHas(string $key)
    {
        return $this->redis->exists($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSet(string $key, $value, int $ttl = 0)
    {
        $data = Serialize::encode($value);
        if ($ttl > 0) {
            return $this->redis->setEx($key, $ttl, $data);
        }

        return $this->redis->set($key, $data);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(string $key)
    {
        $return = $this->redis->del($key);

        return $return >= 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear()
    {
        return $this->redis->flushDb();
    }
}
