<?php
/**
 * static (a fork of Redisent)
 *
 * Most commands are compatible with phpredis library:
 *   - use "pipeline()" to start a pipeline of commands instead of multi(Redis::PIPELINE)
 *   - any arrays passed as arguments will be flattened automatically
 *   - setOption and getOption are not supported in standalone mode
 *   - order of arguments follows redis-cli instead of phpredis where they differ (lrem)
 *
 * - Uses phpredis library if extension is installed for better performance.
 * - Establishes connection lazily.
 * - Supports tcp and unix sockets.
 * - Reconnects automatically unless a watch or transaction is in progress.
 * - Can set automatic retry connection attempts for iffy Redis connections.
 *
 * @author    Colin Mollenhour <colin@mollenhour.com>
 * @copyright 2011 Colin Mollenhour <colin@mollenhour.com>
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package   static
 */
namespace Hail\Redis\Client;

use Hail\Redis\Exception\RedisException;
use Hail\Util\SafeStorage;


/**
 * static, a lightweight Redis PHP standalone client and phpredis wrapper
 *
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
 * @method bool          set(string $key, string $value)
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
 * @method int           zCard(string $key)
 * @method array         zRangeByScore(string $key, mixed $start, mixed $stop, array $args = null)
 * @method array         zRevRangeByScore(string $key, mixed $start, mixed $stop, array $args = null)
 * @method int           zRemRangeByScore(string $key, mixed $start, mixed $stop)
 * @method array         zRange(string $key, mixed $start, mixed $stop, array $args = null)
 * @method array         zRevRange(string $key, mixed $start, mixed $stop, array $args = null)
 * TODO
 *
 * Pub/Sub
 * @method int           publish(string $channel, string $message)
 * @method int|array     pubsub(string $subCommand, $arg = null)
 * @method void          subscribe(string | array $channels, callable $callback)
 * @method void          pSubscribe(string | array $patterns, callable $callback)
 *
 * Scripting:
 * @method string|int    script(string $command, string $arg1 = null)
 * @method string|int|array|bool eval(string $script, array $keys = null, array $args = null)
 * @method string|int|array|bool evalSha(string $script, array $keys = null, array $args = null)
 */
abstract class AbstractClient
{
	/**
	 * Socket connection to the Redis server or Redis library instance
	 *
	 * @var resource|\Redis
	 */
	protected $redis;

	/**
	 * Host of the Redis server
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * Port on which the Redis server is running
	 *
	 * @var integer
	 */
	protected $port;

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
	 * @var bool
	 */
	protected $closeOnDestruct = true;

	/**
	 * @var bool
	 */
	protected $connected = false;

	/**
	 * @var int
	 */
	protected $maxConnectRetries = 0;

	/**
	 * @var int
	 */
	protected $connectFailures = 0;

	/**
	 * @var SafeStorage
	 */
	protected $safeStorage;

	/**
	 * @var int
	 */
	protected $database = 0;

	/**
	 * @var bool
	 */
	protected $subscribed = false;


	/**
	 * Creates a Redisent connection to the Redis server on host {@link $host} and port {@link $port}.
	 * $host may also be a path to a unix socket or a string in the form of tcp://[hostname]:[port] or unix://[path]
	 *
	 * @param array $config
	 *
	 * @throws RedisException
	 */
	public function __construct($config)
	{
		$this->host = (string) ($config['host'] ?? '127.0.0.1');
		$this->port = (int) ($config['port'] ?? 6379);
		$this->timeout = $config['timeout'] ?? null;
		$this->persistent = (string) ($config['persistent'] ?? '');
		$this->database = (int) ($config['database'] ?? 0);
		$this->readTimeout = (int) ($config['readTimeout'] ?? null);

		$this->safeStorage = new SafeStorage();
		$this->setPassword($config['password'] ?? null);

		if (preg_match('#^(tcp|unix)://(.*)$#', $this->host, $matches)) {
			if ($matches[1] === 'tcp') {
				if (!preg_match('#^([^:]+)(:(\d+))?(/(.+))?$#', $matches[2], $matches)) {
					throw new RedisException('Invalid host format; expected tcp://host[:port][/persistence_identifier]');
				}
				$this->host = $matches[1];
				$this->port = (int) ($matches[3] ?? 6379);
				$this->persistent = $matches[5] ?? '';
			} else {
				$this->host = $matches[2];
				$this->port = null;
				if ($this->host[0] !== '/') {
					throw new RedisException('Invalid unix socket format; expected unix:///path/to/redis.sock');
				}
			}
		}

		if ($this->port !== null && $this->host[0] === '/') {
			$this->port = null;
		}

		$this->connect();
	}

	public function __destruct()
	{
		if ($this->closeOnDestruct) {
			$this->close();
		}
	}

	/**
	 * @return bool
	 */
	public function isSubscribed()
	{
		return $this->subscribed;
	}

	/**
	 * Return the host of the Redis instance
	 *
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}

	/**
	 * Return the port of the Redis instance
	 *
	 * @return int
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * Return the selected database
	 *
	 * @return int
	 */
	public function getDatabase()
	{
		return $this->database;
	}

	/**
	 * @return string
	 */
	public function getPersistence()
	{
		return $this->persistent;
	}

	/**
	 * @param int $retries
	 *
	 * @return static
	 */
	public function setMaxConnectRetries($retries)
	{
		$this->maxConnectRetries = $retries;

		return $this;
	}

	/**
	 * @param bool $flag
	 *
	 * @return static
	 */
	public function setCloseOnDestruct($flag)
	{
		$this->closeOnDestruct = $flag;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isConnected()
	{
		return $this->connected;
	}

	public function setPassword($password)
	{
		$this->safeStorage->set('password', $password);
	}

	public function getPassword()
	{
		return $this->safeStorage->get('password');
	}

	/**
	 * @throws RedisException
	 */
	abstract protected function connect();

	/**
	 * Set the read timeout for the connection. Use 0 to disable timeouts entirely (or use a very long timeout
	 * if not supported).
	 *
	 * @param int $timeout 0 (or -1) for no timeout, otherwise number of seconds
	 *
	 * @throws RedisException
	 * @return static
	 */
	abstract public function setReadTimeout(int $timeout);

	/**
	 * @return bool
	 */
	abstract public function close();

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
	 * @param int $index
	 *
	 * @return bool
	 */
	public function select($index)
	{
		$response = $this->__call('select', [$index]);
		$this->database = (int) $index;

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
	 * @param int    $Iterator
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

	/**
	 * Flatten arguments
	 *
	 * If an argument is an array, the key is inserted as argument followed by the array values
	 *  ['zrangebyscore', '-inf', 123, ['limit' => ['0', '1']]]
	 * becomes
	 *  ['zrangebyscore', '-inf', 123, 'limit', '0', '1']
	 *
	 * @param array $arguments
	 * @param array $out
	 *
	 * @return array
	 */
	protected static function flattenArguments(array $arguments, array $out = []): array
	{
		foreach ($arguments as $key => $arg) {
			if (!is_int($key)) {
				$out[] = $key;
			}

			if (is_array($arg)) {
				$out = self::flattenArguments($arg, $out);
			} else {
				$out[] = $arg;
			}
		}

		return $out;
	}
}
