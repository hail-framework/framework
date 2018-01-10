<?php

namespace Hail\Redis;

/**
 * Server/Connection:
 * @method $this        pipeline()
 * @method $this        multi()
 * @method array         exec()
 * @method string        flushAll()
 * @method string        flushDb()
 * @method array         info(string $section = 'default')
 * @method bool|array    config(string $setGet, string $key, string $value = null)
 * @method array         role()
 * @method array         time()
 *
 * Keys:
 * @method int           del(string $key)
 * @method int           exists(string $key)
 * @method int           expire(string $key, int $seconds)
 * @method int           expireAt(string $key, int $timestamp)
 * @method array         keys(string $key)
 * @method int           persist(string $key)
 * @method bool          rename(string $key, string $newKey)
 * @method bool          renameNx(string $key, string $newKey)
 * @method array         sort(string $key, string $arg1, string $valueN = null)
 * @method int           ttl(string $key)
 * @method string        type(string $key)
 *
 * Scalars:
 * @method int           append(string $key, string $value)
 * @method int           decr(string $key)
 * @method int           decrBy(string $key, int $decrement)
 * @method bool|string   get(string $key)
 * @method int           getBit(string $key, int $offset)
 * @method string        getRange(string $key, int $start, int $end)
 * @method string        getSet(string $key, string $value)
 * @method int           incr(string $key)
 * @method int           incrBy(string $key, int $decrement)
 * @method array         mGet(array $keys)
 * @method bool          mSet(array $keysValues)
 * @method int           mSetNx(array $keysValues)
 * @method bool          set(string $key, string $value, int|array $options = null)
 * @method int           setBit(string $key, int $offset, int $value)
 * @method bool          setEx(string $key, int $seconds, string $value)
 * @method int           setNx(string $key, string $value)
 * @method int           setRange(string $key, int $offset, int $value)
 * @method int           strLen(string $key)
 *
 * Sets:
 * @method int           sAdd(string $key, mixed $value, string $valueN = null)
 * @method int           sRem(string $key, mixed $value, string $valueN = null)
 * @method array         sMembers(string $key)
 * @method array         sUnion(mixed $keyOrArray, string $valueN = null)
 * @method array         sInter(mixed $keyOrArray, string $valueN = null)
 * @method array         sDiff(mixed $keyOrArray, string $valueN = null)
 * @method string        sPop(string $key)
 * @method int           sCard(string $key)
 * @method int           sIsMember(string $key, string $member)
 * @method int           sMove(string $source, string $dest, string $member)
 * @method string|array  sRandMember(string $key, int $count = null)
 * @method int           sUnionStore(string $dest, string $key1, string $key2 = null)
 * @method int           sInterStore(string $dest, string $key1, string $key2 = null)
 * @method int           sDiffStore(string $dest, string $key1, string $key2 = null)
 *
 * Hashes:
 * @method bool|int      hSet(string $key, string $field, string $value)
 * @method bool          hSetNx(string $key, string $field, string $value)
 * @method bool|string   hGet(string $key, string $field)
 * @method bool|int      hLen(string $key)
 * @method bool          hDel(string $key, string $field)
 * @method array         hKeys(string $key, string $field)
 * @method array         hVals(string $key)
 * @method array         hGetAll(string $key)
 * @method bool          hExists(string $key, string $field)
 * @method int           hIncrBy(string $key, string $field, int $value)
 * @method bool          hMSet(string $key, array $keysValues)
 * @method array         hMGet(string $key, array $fields)
 *
 * Lists:
 * @method array|null    blPop(string $keyN, int $timeout)
 * @method array|null    brPop(string $keyN, int $timeout)
 * @method array|null    brPoplPush(string $source, string $destination, int $timeout)
 * @method string|null   lIndex(string $key, int $index)
 * @method int           lInsert(string $key, string $beforeAfter, string $pivot, string $value)
 * @method int           lLen(string $key)
 * @method string|null   lPop(string $key)
 * @method int           lPush(string $key, mixed $value, mixed $valueN = null)
 * @method int           lPushX(string $key, mixed $value)
 * @method array         lRange(string $key, int $start, int $stop)
 * @method int           lRem(string $key, int $count, mixed $value)
 * @method bool          lSet(string $key, int $index, mixed $value)
 * @method bool          lTrim(string $key, int $start, int $stop)
 * @method string|null   rPop(string $key)
 * @method string|null   rPoplPush(string $source, string $destination)
 * @method int           rPush(string $key, mixed $value, mixed $valueN = null)
 * @method int           rPushX(string $key, mixed $value)
 *
 * Sorted Sets:
 * @method int           zAdd(string $key, double $score, string $value)
 * @method int           zCard(string $key)
 * @method int           zSize(string $key)
 * @method int           zCount(string $key, mixed $start, mixed $stop)
 * @method int           zIncrBy(string $key, double $value, string $member)
 * @method array         zRangeByScore(string $key, mixed $start, mixed $stop, array $args = null)
 * @method array         zRevRangeByScore(string $key, mixed $start, mixed $stop, array $args = null)
 * @method int           zRemRangeByScore(string $key, mixed $start, mixed $stop)
 * @method array         zRange(string $key, mixed $start, mixed $stop, array $args = null)
 * @method array         zRevRange(string $key, mixed $start, mixed $stop, array $args = null)
 * @method int           zRank(string $key, string $member)
 * @method int           zRevRank(string $key, string $member)
 * @method int           zRem(string $key, string $member)
 * @method int           zDelete(string $key, string $member)
 * @method int           zUnionStore(string $destination, array $keys, array $options = [])
 * TODO
 *
 * Pub/Sub
 * @method int           publish(string $channel, string $message)
 * @method int|array     pubsub(string $subCommand, $arg = null)
 * @method void          subscribe(string|array $channels, callable $callback)
 * @method void          pSubscribe(string|array $patterns, callable $callback)
 *
 * Scripting:
 * @method string|int    script(string $command, string $arg1 = null)
 * @method string|int|array|bool eval(string $script, array $keys = null, array $args = null)
 * @method string|int|array|bool evalSha(string $script, array $keys = null, array $args = null)
 */
interface RedisInterface
{
    /**
     * @param string $password
     *
     * @return bool
     */
    public function auth($password);

    /**
     * @param array ...$pattern
     *
     * @return array
     */
    public function pUnsubscribe(...$pattern);

    /**
     * @param int    $Iterator
     * @param string $pattern
     * @param int    $Iterator
     *
     * @return bool | array
     */
    public function scan(&$Iterator, $pattern = null, $count = null);

    /**
     * @param int    $Iterator
     * @param string $field
     * @param string $pattern
     * @param int    $count
     *
     * @return bool | array
     */
    public function hscan(&$Iterator, $field, $pattern = null, $count = null);

    /**
     * @param int    $Iterator
     * @param string $field
     * @param string $pattern
     * @param int    $Iterator
     *
     * @return bool | array
     */
    public function sscan(&$Iterator, $field, $pattern = null, $count = null);

    /**
     * @param int    $Iterator
     * @param string $field
     * @param string $pattern
     * @param int    $Iterator
     *
     * @return bool | array
     */
    public function zscan(&$Iterator, $field, $pattern = null, $count = null);

    /**
     * @param array ...$pattern
     *
     * @return array
     */
    public function unsubscribe(...$pattern);

    public function __call($name, $args);
}