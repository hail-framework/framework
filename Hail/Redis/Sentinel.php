<?php
namespace Hail\Redis;
use Hail\Redis\Driver\Native;
use Hail\Redis\Exception\RedisException;
use Hail\Util\SafeStorage;

/**
 * Sentinel
 *
 * Implements the Sentinel API as mentioned on http://redis.io/topics/sentinel.
 * Sentinel is aware of master and slave nodes in a cluster and returns instances of Client accordingly.
 *
 * The complexity of read/write splitting can also be abstract by calling the createCluster() method which returns a
 * Cluster object that contains both the master server and a random slave. Cluster takes care of the
 * read/write splitting
 *
 * @author  Thijs Feryn <thijs@feryn.eu>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package Sentinel
 */
class Sentinel
{
	/**
	 * Contains a client that connects to a Sentinel node.
	 * Sentinel uses the same protocol as Redis which makes using Client convenient.
	 *
	 * @var Native
	 */
	protected $_client;

	/**
	 * Contains an active instance of Cluster per master pool
	 *
	 * @var array
	 */
	protected $_cluster = [];

	/**
	 * Contains an active instance of Client representing a master
	 *
	 * @var array
	 */
	protected $_master = [];

	/**
	 * Contains an array Client objects representing all slaves per master pool
	 *
	 * @var array
	 */
	protected $_slaves = [];

	/**
	 * Store the AUTH password used by Client instances
	 *
	 * @var SafeStorage
	 */
	protected $safeStorage;

	/**
	 * Connect with a Sentinel node. Sentinel will do the master and slave discovery
	 *
	 * @param Native $client
	 * @param string $password (deprecated - use setClientPassword)
	 *
	 * @throws RedisException
	 */
	public function __construct(Native $client, $password = null)
	{
		if (!$client instanceof Native) {
			throw new RedisException('Sentinel client should be an instance of Hail\Redis\Driver\Native');
		}

		$this->_client = $client;
		$this->_timeout = null;
		$this->_persistent = '';
		$this->_db = 0;

		$this->safeStorage = new SafeStorage();
		$this->setClientPassword($password);
	}

	/**
	 * @param float $timeout
	 *
	 * @return $this
	 */
	public function setClientTimeout($timeout)
	{
		$this->_timeout = $timeout;

		return $this;
	}

	/**
	 * @param string $persistent
	 *
	 * @return $this
	 */
	public function setClientPersistent($persistent)
	{
		$this->_persistent = $persistent;

		return $this;
	}

	/**
	 * @param int $db
	 *
	 * @return $this
	 */
	public function setClientDatabase($db)
	{
		$this->_db = $db;

		return $this;
	}

	/**
	 * @param null|string $password
	 *
	 * @return $this
	 */
	public function setClientPassword($password)
	{
		$this->safeStorage->set('password', $password);

		return $this;
	}

	public function getClientPassword()
	{
		return $this->safeStorage->get('password');
	}

	/**
	 * Discover the master node automatically and return an instance of Client that connects to the master
	 *
	 * @param string $name
	 *
	 * @return Driver
	 * @throws RedisException
	 */
	public function createMasterClient($name)
	{
		$master = $this->getMasterAddressByName($name);
		if (!isset($master[0], $master[1])) {
			throw new RedisException('Master not found');
		}

		return Factory::client([
			'host' => $master[0],
			'port' => $master[1],
			'timeout' => $this->_timeout,
			'persistent' => $this->_persistent,
			'db' => $this->_db,
			'password' => $this->_password
		]);
	}

	/**
	 * If a Client object exists for a master, return it. Otherwise create one and return it
	 *
	 * @param string $name
	 *
	 * @return Driver
	 */
	public function getMasterClient($name)
	{
		if (!isset($this->_master[$name])) {
			$this->_master[$name] = $this->createMasterClient($name);
		}

		return $this->_master[$name];
	}

	/**
	 * Discover the slave nodes automatically and return an array of Client objects
	 *
	 * @param string $name
	 *
	 * @return Driver[]
	 * @throws RedisException
	 */
	public function createSlaveClients($name)
	{
		$slaves = $this->slaves($name);
		$workingSlaves = [];
		foreach ($slaves as $slave) {
			if (!isset($slave[9])) {
				throw new RedisException('Can\' retrieve slave status');
			}
			if (false === strpos($slave[9], 's_down') && false === strpos($slave[9], 'disconnected')) {
				$workingSlaves[] = Factory::client([
					'host' => $slave[3],
					'port' => $slave[5],
					'timeout' => $this->_timeout,
					'persistent' => $this->_persistent,
					'db' => $this->_db,
					'password' => $this->getClientPassword()
				]);
			}
		}

		return $workingSlaves;
	}

	/**
	 * If an array of Client objects exist for a set of slaves, return them. Otherwise create and return them
	 *
	 * @param string $name
	 *
	 * @return Driver[]
	 */
	public function getSlaveClients($name)
	{
		if (!isset($this->_slaves[$name])) {
			$this->_slaves[$name] = $this->createSlaveClients($name);
		}

		return $this->_slaves[$name];
	}

	/**
	 * Returns a Redis cluster object containing a random slave and the master
	 * When $selectRandomSlave is true, only one random slave is passed.
	 * When $selectRandomSlave is false, all clients are passed and hashing is applied in Cluster
	 * When $writeOnly is false, the master server will also be used for read commands.
	 *
	 * @param string $name
	 * @param int    $db
	 * @param int    $replicas
	 * @param bool   $selectRandomSlave
	 * @param bool   $writeOnly
	 *
	 * @return Cluster
	 * @throws RedisException
	 * @deprecated
	 */
	public function createCluster($name, $db = 0, $replicas = 128, $selectRandomSlave = true, $writeOnly = false)
	{
		$clients = [];
		$workingClients = [];
		$master = $this->master($name);
		if (false !== strpos($master[9], 's_down') || false !== strpos($master[9], 'disconnected')) {
			throw new RedisException('The master is down');
		}
		$slaves = $this->slaves($name);
		foreach ($slaves as $slave) {
			if (false === strpos($slave[9], 's_down') && false === strpos($slave[9], 'disconnected')) {
				$workingClients[] = ['host' => $slave[3], 'port' => $slave[5], 'master' => false, 'db' => $db, 'password' => $this->getClientPassword()];
			}
		}
		if (count($workingClients) > 0) {
			if ($selectRandomSlave) {
				if (!$writeOnly) {
					$workingClients[] = ['host' => $master[3], 'port' => $master[5], 'master' => false, 'db' => $db, 'password' => $this->getClientPassword()];
				}
				$clients[] = $workingClients[random_int(0, count($workingClients) - 1)];
			} else {
				$clients = $workingClients;
			}
		}
		$clients[] = ['host' => $master[3], 'port' => $master[5], 'db' => $db, 'master' => true, 'write_only' => $writeOnly, 'password' => $this->getClientPassword()];

		return new Cluster($clients, $replicas);
	}

	/**
	 * If a Cluster object exists, return it. Otherwise create one and return it.
	 *
	 * @param string $name
	 * @param int    $db
	 * @param int    $replicas
	 * @param bool   $selectRandomSlave
	 * @param bool   $writeOnly
	 *
	 * @return Cluster
	 * @deprecated
	 */
	public function getCluster($name, $db = 0, $replicas = 128, $selectRandomSlave = true, $writeOnly = false)
	{
		if (!isset($this->_cluster[$name])) {
			$this->_cluster[$name] = $this->createCluster($name, $db, $replicas, $selectRandomSlave, $writeOnly);
		}

		return $this->_cluster[$name];
	}

	/**
	 * Catch-all method
	 *
	 * @param string $name
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function __call($name, $args)
	{
		array_unshift($args, $name);

		return call_user_func([$this->_client, 'sentinel'], $args);
	}

	/**
	 * Return information about all registered master servers
	 *
	 * @return mixed
	 */
	public function masters()
	{
		return $this->_client->sentinel('masters');
	}

	/**
	 * Return all information for slaves that are associated with a single master
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function slaves($name)
	{
		return $this->_client->sentinel('slaves', $name);
	}

	/**
	 * Get the information for a specific master
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function master($name)
	{
		return $this->_client->sentinel('master', $name);
	}

	/**
	 * Get the hostname and port for a specific master
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function getMasterAddressByName($name)
	{
		return $this->_client->sentinel('get-master-addr-by-name', $name);
	}

	/**
	 * Check if the Sentinel is still responding
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function ping()
	{
		return $this->_client->ping();
	}

	/**
	 * Perform an auto-failover which will re-elect another master and make the current master a slave
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function failover($name)
	{
		return $this->_client->sentinel('failover', $name);
	}

	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->_client->getHost();
	}

	/**
	 * @return int
	 */
	public function getPort()
	{
		return $this->_client->getPort();
	}
}
