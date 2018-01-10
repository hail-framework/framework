<?php
/*
 * This file some codes from the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Redis\Cluster;

use Hail\Redis\Client\Native as Client;
use Hail\Redis\Exception\RedisException;

class Native extends AbstractCluster
{
    protected $commands;

    /**
     * @var Client[]
     */
    private $pool = [];

    private $slots = [];
    private $slotsMap = [];

    /**
     * -1 = unlimited retry attempts
     *  0 = no retry attempts (fails immediatly)
     *  n = fail only after n retry attempts
     *
     * @var int Number of retry attempts.
     */
    private $retryLimit;

    public function __construct($config)
    {
        parent::__construct($config);

        $this->commands = $this->getDefaultCommands();
        $this->retryLimit = $config['retry'] ?? 5;
    }

    public function isConnected()
    {
        foreach ($this->pool as $connection) {
            if ($connection->isConnected()) {
                return true;
            }
        }

        $connection = $this->getRandomConnection();

        return ($connection && $connection->isConnected());
    }

    public function connect()
    {
        $this->getRandomConnection();
    }

    public function close()
    {
        foreach ($this->pool as $connection) {
            $connection->close();
        }
    }

    public function add(Client $connection)
    {
        $this->pool[(string) $connection] = $connection;
        $this->slotsMap = [];
    }

    public function remove(Client $connection)
    {
        if (false !== $id = \array_search($connection, $this->pool, true)) {
            unset($this->pool[$id]);
            $this->slotsMap = [];
            $this->slots = \array_diff($this->slots, [$connection]);

            return true;
        }

        return false;
    }

    public function flushDb()
    {
        foreach ($this->hosts as $connectionID) {
            if (!$connection = $this->getConnectionById($connectionID)) {
                $connection = $this->createConnection($connectionID);
            }

            if ('OK' !== $connection->flushDb()) {
                return false;
            }
        }

        return true;
    }

    public function flushAll()
    {
        foreach ($this->hosts as $connectionID) {
            if (!$connection = $this->getConnectionById($connectionID)) {
                $connection = $this->createConnection($connectionID);
            }

            if ('OK' !== $connection->flushAll()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Queries the specified node of the cluster to fetch the updated slots map.
     *
     * When the connection fails, this method tries to execute the same command
     * on a different connection picked at random from the pool of known nodes,
     * up until the retry limit is reached.
     *
     * @param Client $connection Connection to a node of the cluster.
     *
     * @return mixed
     * @throws RedisException
     */
    private function queryClusterNodeForSlotsMap(Client $connection)
    {
        $retries = 0;

        RETRY_COMMAND:
        {
            try {
                $response = $connection->execute('cluster', ['slots']);
            } catch (RedisException $exception) {
                $connection->close();
                $this->remove($connection);
                if ($retries === $this->retryLimit) {
                    throw $exception;
                }

                if (!$connection = $this->getRandomConnection()) {
                    throw new RedisException('No connections left in the pool for `CLUSTER SLOTS`');
                }

                ++$retries;
                goto RETRY_COMMAND;
            }
        }

        return $response;
    }

    /**
     * Generates an updated slots map fetching the cluster configuration using
     * the CLUSTER SLOTS command against the specified node or a random one from
     * the pool.
     *
     * @param Client|null $connection Optional connection instance.
     *
     * @return array
     */
    public function askSlotsMap(Client $connection = null)
    {
        $connection = $connection ?? $this->getRandomConnection();
        if (!$connection) {
            return [];
        }

        $response = $this->queryClusterNodeForSlotsMap($connection);

        $this->hosts = $this->slotsMap = [];
        foreach ($response as $slots) {
            // We only support master servers for now, so we ignore subsequent
            // elements in the $slots array identifying slaves.
            [$start, $end, $master] = $slots;

            if ($master[0] === '') {
                $this->setSlots($start, $end, (string) $connection);
            } else {
                $this->setSlots($start, $end, "{$master[0]}:{$master[1]}");
            }
        }

        return $this->slotsMap;
    }

    /**
     * Resets the slots map cache.
     */
    public function resetSlotsMap()
    {
        $this->slotsMap = [];
    }

    /**
     * Returns the current slots map for the cluster.
     *
     * The order of the returned $slot => $server dictionary is not guaranteed.
     *
     * @return array
     */
    public function getSlotsMap()
    {
        return $this->slotsMap;
    }

    /**
     * Pre-associates a connection to a slots range to avoid runtime guessing.
     *
     * @param int           $first      Initial slot of the range.
     * @param int           $last       Last slot of the range.
     * @param Client|string $connection ID or connection instance.
     *
     * @throws \OutOfBoundsException
     */
    public function setSlots($first, $last, $connection)
    {
        if ($first < 0x0000 || $first > 0x3FFF ||
            $last < 0x0000 || $last > 0x3FFF ||
            $last < $first
        ) {
            throw new \OutOfBoundsException(
                "Invalid slot range for $connection: [$first-$last]."
            );
        }

        if (!\in_array($connection, $this->hosts, true)) {
            $this->hosts[] = $connection;
        }

        $slots = \array_fill($first, $last - $first + 1, (string) $connection);
        $this->slotsMap += $slots;
    }

    /**
     * Guesses the correct node associated to a given slot using a precalculated
     * slots map, falling back to the same logic used by Redis to initialize a
     * cluster (best-effort).
     *
     * @param int $slot Slot index.
     *
     * @return string Connection ID.
     * @throws RedisException
     */
    protected function guessNode($slot)
    {
        if (isset($this->slotsMap[$slot])) {
            return $this->slotsMap[$slot];
        }

        $count = \count($this->hosts);
        $index = \min((int) ($slot / (int) (16384 / $count)), $count - 1);
        $nodes = \array_values($this->hosts);

        return $nodes[$index];
    }

    /**
     * Creates a new connection instance from the given connection ID.
     *
     * @param string $connectionID Identifier for the connection.
     *
     * @return Client
     */
    protected function createConnection($connectionID)
    {
        $host = \explode(':', $connectionID, 2);

        $config = [
            'host' => $host[0],
            'port' => $host[1] ?? 6379,
            'timeout' => $this->timeout,
            'persistent' => $this->persistent,
            'readTimeout' => $this->readTimeout,
            'password' => $this->getPassword(),
        ];

        $connection = new Client($config);
        $connection->inCluster();

        if ([] === $this->slotsMap) {
            $this->askSlotsMap($connection);
        }

        return $this->pool[$connectionID] = $connection;
    }

    /**
     * @param $command
     * @param $args
     *
     * @return Client|mixed|null
     * @throws RedisException
     * @throws \BadMethodCallException
     */
    public function getConnection($command, $args)
    {
        $slot = $this->getSlot($command, $args);
        if (null === $slot) {
            throw new \BadMethodCallException(
                "Cannot use '{$command}' with redis-cluster."
            );
        }

        if (isset($this->slots[$slot])) {
            return $this->slots[$slot];
        }

        return $this->getConnectionBySlot($slot);
    }

    /**
     * Returns the connection currently associated to a given slot.
     *
     * @param int $slot Slot index.
     *
     * @return Client|mixed|null
     * @throws RedisException
     */
    public function getConnectionBySlot($slot)
    {
        if ($slot < 0x0000 || $slot > 0x3FFF) {
            throw new \OutOfBoundsException("Invalid slot [$slot].");
        }

        if (isset($this->slots[$slot])) {
            return $this->slots[$slot];
        }

        $connectionID = $this->guessNode($slot);
        if (!$connection = $this->getConnectionById($connectionID)) {
            $connection = $this->createConnection($connectionID);
        }

        return $this->slots[$slot] = $connection;
    }

    public function getConnectionById($connectionID)
    {
        if (isset($this->pool[$connectionID])) {
            return $this->pool[$connectionID];
        }

        return null;
    }

    /**
     * Returns a random connection from the pool.
     *
     * @return Client|null
     */
    protected function getRandomConnection(): ?Client
    {
        if ($this->pool) {
            return $this->pool[\array_rand($this->pool)];
        }

        if ($this->hosts) {
            $key = \array_rand($this->hosts);
            $connectionID = $this->hosts[$key];
            unset($key);

            return $this->createConnection($connectionID);
        }

        return null;
    }

    /**
     * Permanently associates the connection instance to a new slot.
     * The connection is added to the connections pool if not yet included.
     *
     * @param Client $connection Connection instance.
     * @param int    $slot       Target slot index.
     */
    protected function move(Client $connection, $slot)
    {
        $this->pool[(string) $connection] = $connection;
        $this->slots[(int) $slot] = $connection;
    }

    /**
     * Handles -ERR responses returned by Redis.
     *
     * @param string         $command Command that generated the -ERR response.
     * @param array          $args
     * @param RedisException $error   Redis error response object.
     *
     * @return mixed
     * @throws RedisException
     */
    protected function onErrorResponse(string $command, array $args, RedisException $error)
    {
        $details = \explode(' ', $error->getMessage(), 2);
        switch ($details[0]) {
            case 'MOVED':
                return $this->onMovedResponse($command, $args, $details[1]);
            case 'ASK':
                return $this->onAskResponse($command, $args, $details[1]);
            default:
                throw $error;
        }
    }

    /**
     * Handles -MOVED responses by executing again the command against the node
     * indicated by the Redis response.
     *
     * @param string $command Command that generated the -MOVED response.
     * @param array  $args
     * @param string $details Parameters of the -MOVED response.
     *
     * @return mixed
     * @throws RedisException
     */
    protected function onMovedResponse(string $command, array $args, string $details)
    {
        [$slot, $connectionID] = \explode(' ', $details, 2);
        if (!$connection = $this->getConnectionById($connectionID)) {
            $connection = $this->createConnection($connectionID);
        }

        $this->askSlotsMap($connection);
        $this->move($connection, $slot);

        return $this->execute($command, $args);
    }

    /**
     * Handles -ASK responses by executing again the command against the node
     * indicated by the Redis response.
     *
     * @param string $command Command that generated the -ASK response.
     * @param array  $args
     * @param string $details Parameters of the -ASK response.
     *
     * @return mixed
     * @throws RedisException
     */
    protected function onAskResponse(string $command, array $args, string $details)
    {
        [$slot, $connectionID] = \explode(' ', $details, 2);
        if (!$connection = $this->getConnectionById($connectionID)) {
            $connection = $this->createConnection($connectionID);
        }

        return $connection->execute('asking');
    }

    /**
     * @param       $name
     * @param       $args
     * @param array $trackedArgs
     *
     * @return $this|array|bool|mixed|null|string
     * @throws RedisException
     */
    public function execute($name, $args, $trackedArgs = [])
    {
        $connection = $this->getConnection($name, $args);

        $failure = false;
        RETRY_COMMAND:
        {
            try {
                $response = $connection->execute($name, $args, $trackedArgs);
            } catch (RedisException $exception) {
                $connection->close();
                $this->remove($connection);
                if ($failure) {
                    throw $exception;
                }

                $this->askSlotsMap();
                $failure = true;
                goto RETRY_COMMAND;
            }
        }

        if ($response instanceof RedisException) {
            return $this->onErrorResponse($name, $args, $response);
        }

        return $response;
    }

    /**
     * @param $name
     * @param $args
     *
     * @return array|bool|Native|mixed|null|string
     * @throws RedisException
     */
    public function __call($name, $args)
    {
        [$name, $args, $trackedArgs] = Client::normalize($name, $args);

        return $this->execute($name, $args, $trackedArgs);
    }

    public function count()
    {
        return \count($this->pool);
    }

    /**
     * Returns the default map of supported commands with their handlers.
     *
     * @return array
     */
    protected function getDefaultCommands()
    {
        $getKeyFromFirstArgument = [$this, 'getKeyFromFirstArgument'];
        $getKeyFromAllArguments = [$this, 'getKeyFromAllArguments'];

        return [
            /* commands operating on the key space */
            'EXISTS' => $getKeyFromAllArguments,
            'DEL' => $getKeyFromAllArguments,
            'TYPE' => $getKeyFromFirstArgument,
            'EXPIRE' => $getKeyFromFirstArgument,
            'EXPIREAT' => $getKeyFromFirstArgument,
            'PERSIST' => $getKeyFromFirstArgument,
            'PEXPIRE' => $getKeyFromFirstArgument,
            'PEXPIREAT' => $getKeyFromFirstArgument,
            'TTL' => $getKeyFromFirstArgument,
            'PTTL' => $getKeyFromFirstArgument,
            'SORT' => [$this, 'getKeyFromSortCommand'],
            'DUMP' => $getKeyFromFirstArgument,
            'RESTORE' => $getKeyFromFirstArgument,

            /* commands operating on string values */
            'APPEND' => $getKeyFromFirstArgument,
            'DECR' => $getKeyFromFirstArgument,
            'DECRBY' => $getKeyFromFirstArgument,
            'GET' => $getKeyFromFirstArgument,
            'GETBIT' => $getKeyFromFirstArgument,
            'MGET' => $getKeyFromAllArguments,
            'SET' => $getKeyFromFirstArgument,
            'GETRANGE' => $getKeyFromFirstArgument,
            'GETSET' => $getKeyFromFirstArgument,
            'INCR' => $getKeyFromFirstArgument,
            'INCRBY' => $getKeyFromFirstArgument,
            'INCRBYFLOAT' => $getKeyFromFirstArgument,
            'SETBIT' => $getKeyFromFirstArgument,
            'SETEX' => $getKeyFromFirstArgument,
            'MSET' => [$this, 'getKeyFromInterleavedArguments'],
            'MSETNX' => [$this, 'getKeyFromInterleavedArguments'],
            'SETNX' => $getKeyFromFirstArgument,
            'SETRANGE' => $getKeyFromFirstArgument,
            'STRLEN' => $getKeyFromFirstArgument,
            'SUBSTR' => $getKeyFromFirstArgument,
            'BITOP' => [$this, 'getKeyFromBitOp'],
            'BITCOUNT' => $getKeyFromFirstArgument,
            'BITFIELD' => $getKeyFromFirstArgument,

            /* commands operating on lists */
            'LINSERT' => $getKeyFromFirstArgument,
            'LINDEX' => $getKeyFromFirstArgument,
            'LLEN' => $getKeyFromFirstArgument,
            'LPOP' => $getKeyFromFirstArgument,
            'RPOP' => $getKeyFromFirstArgument,
            'RPOPLPUSH' => $getKeyFromAllArguments,
            'BLPOP' => [$this, 'getKeyFromBlockingListCommands'],
            'BRPOP' => [$this, 'getKeyFromBlockingListCommands'],
            'BRPOPLPUSH' => [$this, 'getKeyFromBlockingListCommands'],
            'LPUSH' => $getKeyFromFirstArgument,
            'LPUSHX' => $getKeyFromFirstArgument,
            'RPUSH' => $getKeyFromFirstArgument,
            'RPUSHX' => $getKeyFromFirstArgument,
            'LRANGE' => $getKeyFromFirstArgument,
            'LREM' => $getKeyFromFirstArgument,
            'LSET' => $getKeyFromFirstArgument,
            'LTRIM' => $getKeyFromFirstArgument,

            /* commands operating on sets */
            'SADD' => $getKeyFromFirstArgument,
            'SCARD' => $getKeyFromFirstArgument,
            'SDIFF' => $getKeyFromAllArguments,
            'SDIFFSTORE' => $getKeyFromAllArguments,
            'SINTER' => $getKeyFromAllArguments,
            'SINTERSTORE' => $getKeyFromAllArguments,
            'SUNION' => $getKeyFromAllArguments,
            'SUNIONSTORE' => $getKeyFromAllArguments,
            'SISMEMBER' => $getKeyFromFirstArgument,
            'SMEMBERS' => $getKeyFromFirstArgument,
            'SSCAN' => $getKeyFromFirstArgument,
            'SPOP' => $getKeyFromFirstArgument,
            'SRANDMEMBER' => $getKeyFromFirstArgument,
            'SREM' => $getKeyFromFirstArgument,

            /* commands operating on sorted sets */
            'ZADD' => $getKeyFromFirstArgument,
            'ZCARD' => $getKeyFromFirstArgument,
            'ZCOUNT' => $getKeyFromFirstArgument,
            'ZINCRBY' => $getKeyFromFirstArgument,
            'ZINTERSTORE' => [$this, 'getKeyFromZsetAggregationCommands'],
            'ZRANGE' => $getKeyFromFirstArgument,
            'ZRANGEBYSCORE' => $getKeyFromFirstArgument,
            'ZRANK' => $getKeyFromFirstArgument,
            'ZREM' => $getKeyFromFirstArgument,
            'ZREMRANGEBYRANK' => $getKeyFromFirstArgument,
            'ZREMRANGEBYSCORE' => $getKeyFromFirstArgument,
            'ZREVRANGE' => $getKeyFromFirstArgument,
            'ZREVRANGEBYSCORE' => $getKeyFromFirstArgument,
            'ZREVRANK' => $getKeyFromFirstArgument,
            'ZSCORE' => $getKeyFromFirstArgument,
            'ZUNIONSTORE' => [$this, 'getKeyFromZsetAggregationCommands'],
            'ZSCAN' => $getKeyFromFirstArgument,
            'ZLEXCOUNT' => $getKeyFromFirstArgument,
            'ZRANGEBYLEX' => $getKeyFromFirstArgument,
            'ZREMRANGEBYLEX' => $getKeyFromFirstArgument,
            'ZREVRANGEBYLEX' => $getKeyFromFirstArgument,

            /* commands operating on hashes */
            'HDEL' => $getKeyFromFirstArgument,
            'HEXISTS' => $getKeyFromFirstArgument,
            'HGET' => $getKeyFromFirstArgument,
            'HGETALL' => $getKeyFromFirstArgument,
            'HMGET' => $getKeyFromFirstArgument,
            'HMSET' => $getKeyFromFirstArgument,
            'HINCRBY' => $getKeyFromFirstArgument,
            'HINCRBYFLOAT' => $getKeyFromFirstArgument,
            'HKEYS' => $getKeyFromFirstArgument,
            'HLEN' => $getKeyFromFirstArgument,
            'HSET' => $getKeyFromFirstArgument,
            'HSETNX' => $getKeyFromFirstArgument,
            'HVALS' => $getKeyFromFirstArgument,
            'HSCAN' => $getKeyFromFirstArgument,
            'HSTRLEN' => $getKeyFromFirstArgument,

            /* commands operating on HyperLogLog */
            'PFADD' => $getKeyFromFirstArgument,
            'PFCOUNT' => $getKeyFromAllArguments,
            'PFMERGE' => $getKeyFromAllArguments,

            /* scripting */
            'EVAL' => [$this, 'getKeyFromScriptingCommands'],
            'EVALSHA' => [$this, 'getKeyFromScriptingCommands'],

            /* commands performing geospatial operations */
            'GEOADD' => $getKeyFromFirstArgument,
            'GEOHASH' => $getKeyFromFirstArgument,
            'GEOPOS' => $getKeyFromFirstArgument,
            'GEODIST' => $getKeyFromFirstArgument,
            'GEORADIUS' => [$this, 'getKeyFromGeoradiusCommands'],
            'GEORADIUSBYMEMBER' => [$this, 'getKeyFromGeoradiusCommands'],
        ];
    }

    /**
     * Returns the list of IDs for the supported commands.
     *
     * @return array
     */
    public function getSupportedCommands()
    {
        return \array_keys($this->commands);
    }

    /**
     * Sets an handler for the specified command ID.
     *
     * The signature of the callback must have a single parameter of type
     * Predis\Command\CommandInterface.
     *
     * When the callback argument is omitted or NULL, the previously associated
     * handler for the specified command ID is removed.
     *
     * @param string $commandID Command ID.
     * @param mixed  $callback  A valid callable object, or NULL to unset the handler.
     *
     * @throws \InvalidArgumentException
     */
    public function setCommandHandler($commandID, $callback = null)
    {
        $commandID = \strtoupper($commandID);

        if (!isset($callback)) {
            unset($this->commands[$commandID]);

            return;
        }

        if (!\is_callable($callback)) {
            throw new \InvalidArgumentException(
                'The argument must be a callable object or NULL.'
            );
        }

        $this->commands[$commandID] = $callback;
    }

    /**
     * Extracts the key from the first argument of a command instance.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string
     */
    protected function getKeyFromFirstArgument(string $command, array $arguments)
    {
        return $arguments[0];
    }

    /**
     * Extracts the key from a command with multiple keys only when all keys in
     * the arguments array produce the same hash.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string|null
     */
    protected function getKeyFromAllArguments(string $command, array $arguments)
    {
        if ($this->checkSameSlotForKeys($arguments)) {
            return $arguments[0];
        }

        return null;
    }

    /**
     * Extracts the key from a command with multiple keys only when all keys in
     * the arguments array produce the same hash.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string|null
     */
    protected function getKeyFromInterleavedArguments(string $command, array $arguments)
    {
        $keys = [];

        for ($i = 0, $n = \count($arguments); $i < $n; $i += 2) {
            $keys[] = $arguments[$i];
        }

        if ($this->checkSameSlotForKeys($keys)) {
            return $arguments[0];
        }

        return null;
    }

    /**
     * Extracts the key from SORT command.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string|null
     */
    protected function getKeyFromSortCommand(string $command, array $arguments)
    {
        $firstKey = $arguments[0];

        if (1 === $argc = \count($arguments)) {
            return $firstKey;
        }

        $keys = [$firstKey];

        for ($i = 1; $i < $argc; ++$i) {
            if (\strtoupper($arguments[$i]) === 'STORE') {
                $keys[] = $arguments[++$i];
            }
        }

        if ($this->checkSameSlotForKeys($keys)) {
            return $firstKey;
        }

        return null;
    }

    /**
     * Extracts the key from BLPOP and BRPOP commands.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string|null
     */
    protected function getKeyFromBlockingListCommands(string $command, array $arguments)
    {
        if ($this->checkSameSlotForKeys(\array_slice($arguments, 0, \count($arguments) - 1))) {
            return $arguments[0];
        }

        return null;
    }

    /**
     * Extracts the key from BITOP command.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string|null
     */
    protected function getKeyFromBitOp(string $command, array $arguments)
    {
        if ($this->checkSameSlotForKeys(\array_slice($arguments, 1, \count($arguments)))) {
            return $arguments[1];
        }

        return null;
    }

    /**
     * Extracts the key from GEORADIUS and GEORADIUSBYMEMBER commands.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string|null
     */
    protected function getKeyFromGeoradiusCommands(string $command, array $arguments)
    {
        $argc = \count($arguments);
        $startIndex = $command === 'GEORADIUS' ? 5 : 4;

        if ($argc > $startIndex) {
            $keys = [$arguments[0]];

            for ($i = $startIndex; $i < $argc; ++$i) {
                $argument = \strtoupper($arguments[$i]);
                if ($argument === 'STORE' || $argument === 'STOREDIST') {
                    $keys[] = $arguments[++$i];
                }
            }

            if ($this->checkSameSlotForKeys($keys)) {
                return $arguments[0];
            }

            return null;
        }

        return $arguments[0];
    }

    /**
     * Extracts the key from ZINTERSTORE and ZUNIONSTORE commands.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string|null
     */
    protected function getKeyFromZsetAggregationCommands(string $command, array $arguments)
    {
        $keys = \array_merge([$arguments[0]], \array_slice($arguments, 2, $arguments[1]));

        if ($this->checkSameSlotForKeys($keys)) {
            return $arguments[0];
        }

        return null;
    }

    /**
     * Extracts the key from EVAL and EVALSHA commands.
     *
     * @param string $command Command instance.
     * @param array  $arguments
     *
     * @return string|null
     */
    protected function getKeyFromScriptingCommands(string $command, array $arguments)
    {
        $keys = \array_slice($arguments, 2, $arguments[1]);

        if ($keys && $this->checkSameSlotForKeys($keys)) {
            return $keys[0];
        }

        return null;
    }

    /**
     * Returns a slot for the given command used for clustering distribution or
     * NULL when this is not possible.
     *
     * @param string $command Command name.
     * @param array  $args
     *
     * @return int|null
     */
    public function getSlot($command, $args): ?int
    {
        $id = \strtoupper($command);

        $slot = null;

        if (isset($this->commands[$id])) {
            $key = $this->commands[$id]($id, $args);

            if (null !== $key) {
                $slot = $this->getSlotByKey($key);
            }
        }

        return $slot;
    }

    /**
     * Checks if the specified array of keys will generate the same hash.
     *
     * @param array $keys Array of keys.
     *
     * @return bool
     */
    protected function checkSameSlotForKeys(array $keys)
    {
        if (!$count = \count($keys)) {
            return false;
        }

        $currentSlot = $this->getSlotByKey($keys[0]);

        for ($i = 1; $i < $count; ++$i) {
            $nextSlot = $this->getSlotByKey($keys[$i]);

            if ($currentSlot !== $nextSlot) {
                return false;
            }

            $currentSlot = $nextSlot;
        }

        return true;
    }

    /**
     * Returns only the hashable part of a key (delimited by "{...}"), or the
     * whole key if a key tag is not found in the string.
     *
     * @param string $key A key.
     *
     * @return string
     */
    protected function extractKeyTag(string $key): string
    {
        if (false !== $start = \strpos($key, '{')) {
            if (false !== ($end = \strpos($key, '}', $start)) && $end !== ++$start) {
                $key = \substr($key, $start, $end - $start);
            }
        }

        return $key;
    }

    /**
     * Returns a slot for the given key used for clustering distribution or NULL
     * when this is not possible.
     *
     * @param string $key Key string.
     *
     * @return int
     */
    public function getSlotByKey($key)
    {
        $key = $this->extractKeyTag($key);

        return self::crc16($key) & 0x3FFF;
    }

    private static $CCITT_16 = [
        0x0000, 0x1021, 0x2042, 0x3063, 0x4084, 0x50A5, 0x60C6, 0x70E7,
        0x8108, 0x9129, 0xA14A, 0xB16B, 0xC18C, 0xD1AD, 0xE1CE, 0xF1EF,
        0x1231, 0x0210, 0x3273, 0x2252, 0x52B5, 0x4294, 0x72F7, 0x62D6,
        0x9339, 0x8318, 0xB37B, 0xA35A, 0xD3BD, 0xC39C, 0xF3FF, 0xE3DE,
        0x2462, 0x3443, 0x0420, 0x1401, 0x64E6, 0x74C7, 0x44A4, 0x5485,
        0xA56A, 0xB54B, 0x8528, 0x9509, 0xE5EE, 0xF5CF, 0xC5AC, 0xD58D,
        0x3653, 0x2672, 0x1611, 0x0630, 0x76D7, 0x66F6, 0x5695, 0x46B4,
        0xB75B, 0xA77A, 0x9719, 0x8738, 0xF7DF, 0xE7FE, 0xD79D, 0xC7BC,
        0x48C4, 0x58E5, 0x6886, 0x78A7, 0x0840, 0x1861, 0x2802, 0x3823,
        0xC9CC, 0xD9ED, 0xE98E, 0xF9AF, 0x8948, 0x9969, 0xA90A, 0xB92B,
        0x5AF5, 0x4AD4, 0x7AB7, 0x6A96, 0x1A71, 0x0A50, 0x3A33, 0x2A12,
        0xDBFD, 0xCBDC, 0xFBBF, 0xEB9E, 0x9B79, 0x8B58, 0xBB3B, 0xAB1A,
        0x6CA6, 0x7C87, 0x4CE4, 0x5CC5, 0x2C22, 0x3C03, 0x0C60, 0x1C41,
        0xEDAE, 0xFD8F, 0xCDEC, 0xDDCD, 0xAD2A, 0xBD0B, 0x8D68, 0x9D49,
        0x7E97, 0x6EB6, 0x5ED5, 0x4EF4, 0x3E13, 0x2E32, 0x1E51, 0x0E70,
        0xFF9F, 0xEFBE, 0xDFDD, 0xCFFC, 0xBF1B, 0xAF3A, 0x9F59, 0x8F78,
        0x9188, 0x81A9, 0xB1CA, 0xA1EB, 0xD10C, 0xC12D, 0xF14E, 0xE16F,
        0x1080, 0x00A1, 0x30C2, 0x20E3, 0x5004, 0x4025, 0x7046, 0x6067,
        0x83B9, 0x9398, 0xA3FB, 0xB3DA, 0xC33D, 0xD31C, 0xE37F, 0xF35E,
        0x02B1, 0x1290, 0x22F3, 0x32D2, 0x4235, 0x5214, 0x6277, 0x7256,
        0xB5EA, 0xA5CB, 0x95A8, 0x8589, 0xF56E, 0xE54F, 0xD52C, 0xC50D,
        0x34E2, 0x24C3, 0x14A0, 0x0481, 0x7466, 0x6447, 0x5424, 0x4405,
        0xA7DB, 0xB7FA, 0x8799, 0x97B8, 0xE75F, 0xF77E, 0xC71D, 0xD73C,
        0x26D3, 0x36F2, 0x0691, 0x16B0, 0x6657, 0x7676, 0x4615, 0x5634,
        0xD94C, 0xC96D, 0xF90E, 0xE92F, 0x99C8, 0x89E9, 0xB98A, 0xA9AB,
        0x5844, 0x4865, 0x7806, 0x6827, 0x18C0, 0x08E1, 0x3882, 0x28A3,
        0xCB7D, 0xDB5C, 0xEB3F, 0xFB1E, 0x8BF9, 0x9BD8, 0xABBB, 0xBB9A,
        0x4A75, 0x5A54, 0x6A37, 0x7A16, 0x0AF1, 0x1AD0, 0x2AB3, 0x3A92,
        0xFD2E, 0xED0F, 0xDD6C, 0xCD4D, 0xBDAA, 0xAD8B, 0x9DE8, 0x8DC9,
        0x7C26, 0x6C07, 0x5C64, 0x4C45, 0x3CA2, 0x2C83, 0x1CE0, 0x0CC1,
        0xEF1F, 0xFF3E, 0xCF5D, 0xDF7C, 0xAF9B, 0xBFBA, 0x8FD9, 0x9FF8,
        0x6E17, 0x7E36, 0x4E55, 0x5E74, 0x2E93, 0x3EB2, 0x0ED1, 0x1EF0,
    ];

    private static function crc16(string $value): int
    {
        // CRC-CCITT-16 algorithm
        $crc = 0;
        for ($i = 0, $n = \strlen($value); $i < $n; ++$i) {
            $crc = (($crc << 8) ^ self::$CCITT_16[($crc >> 8) ^ \ord($value[$i])]) & 0xFFFF;
        }

        return $crc;
    }

}