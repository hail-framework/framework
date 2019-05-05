<?php
/**
 * Credis_Sentinel
 *
 * Implements the Sentinel API as mentioned on http://redis.io/topics/sentinel.
 * Sentinel is aware of master and slave nodes in a cluster and returns instances of Credis_Client accordingly.
 *
 * The complexity of read/write splitting can also be abstract by calling the createCluster() method which returns a
 * Credis_Cluster object that contains both the master server and a random slave. Credis_Cluster takes care of the
 * read/write splitting
 *
 * @author  Thijs Feryn <thijs@feryn.eu>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package Credis_Sentinel
 */

namespace Hail\Redis;


use Hail\Redis\Client\Native as Client;
use Hail\Redis\Exception\RedisException;
use Hail\SafeStorage\SafeStorageTrait;

class Sentinel
{
    use SafeStorageTrait;

    /**
     * Contains a client that connects to a Sentinel node.
     * Sentinel uses the same protocol as Redis which makes using Credis_Client convenient.
     *
     * @var Client
     */
    protected $client;

    /**
     * Contains an active instance of Credis_Client representing a master
     *
     * @var array
     */
    protected $master = [];
    /**
     * Contains an array Credis_Client objects representing all slaves per master pool
     *
     * @var array
     */
    protected $slaves = [];

    protected $timeout;
    protected $persistent;
    protected $database;

    /**
     * Connect with a Sentinel node. Sentinel will do the master and slave discovery
     */
    public function __construct(array $config, string $password = '')
    {
        $this->client = new Client($config);
        $this->setPassword($password);
        $this->timeout = null;
        $this->persistent = '';
        $this->database = 0;
    }

    /**
     * Clean up client on destruct
     */
    public function __destruct()
    {
        $this->client->close();
    }

    /**
     * @param float $timeout
     *
     * @return $this
     */
    public function setClientTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string $persistent
     *
     * @return $this
     */
    public function setClientPersistent($persistent)
    {
        $this->persistent = $persistent;

        return $this;
    }

    /**
     * @param int $db
     *
     * @return $this
     */
    public function setClientDatabase($db)
    {
        $this->database = $db;

        return $this;
    }

    /**
     * @param null|string $password
     *
     * @return $this
     */
    public function setClientPassword($password)
    {
        return $this->setPassword($password);
    }

    /**
     * Discover the master node automatically and return an instance of Credis_Client that connects to the master
     *
     * @param string $name
     *
     * @return RedisInterface
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
            'timeout' => $this->timeout,
            'persistent' => $this->persistent,
            'database' => $this->database,
            'password' => $this->getPassword(),
        ]);
    }

    /**
     * If a Credis_Client object exists for a master, return it. Otherwise create one and return it
     *
     * @param string $name
     *
     * @return Client
     * @throws RedisException
     */
    public function getMasterClient($name)
    {
        if (!isset($this->master[$name])) {
            $this->master[$name] = $this->createMasterClient($name);
        }

        return $this->master[$name];
    }

    /**
     * Discover the slave nodes automatically and return an array of Credis_Client objects
     *
     * @param string $name
     *
     * @return RedisInterface[]
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
            if (\strpos($slave[9], 's_down') === false && \strpos($slave[9], 'disconnected') === false) {
                $workingSlaves[] = Factory::client([
                    'host' => $slave[3],
                    'port' => $slave[5],
                    'timeout' => $this->timeout,
                    'persistent' => $this->persistent,
                    'database' => $this->database,
                    'password' => $this->getPassword(),
                ]);
            }
        }

        return $workingSlaves;
    }

    /**
     * If an array of Credis_Client objects exist for a set of slaves, return them. Otherwise create and return them
     *
     * @param string $name
     *
     * @return Client[]
     * @throws RedisException
     */
    public function getSlaveClients($name)
    {
        if (!isset($this->slaves[$name])) {
            $this->slaves[$name] = $this->createSlaveClients($name);
        }

        return $this->slaves[$name];
    }

    /**
     * Catch-all method
     *
     * @param string $name
     * @param array  $args
     *
     * @return mixed
     * @throws RedisException
     */
    public function __call($name, $args)
    {
        \array_unshift($args, $name);

        return $this->client->execute('sentinel', $args);
    }

    /**
     * get information block for the sentinel instance
     *
     * @param string|NUll $section
     *
     * @return array
     */
    public function info($section = null)
    {
        if ($section) {
            return $this->client->info($section);
        }

        return $this->client->info();
    }

    /**
     * Return information about all registered master servers
     *
     * @return mixed
     * @throws RedisException
     */
    public function masters()
    {
        return $this->client->execute('sentinel', ['masters']);
    }

    /**
     * Return all information for slaves that are associated with a single master
     *
     * @param string $name
     *
     * @return mixed
     * @throws RedisException
     */
    public function slaves($name)
    {
        return $this->client->execute('sentinel', ['slaves', $name]);
    }

    /**
     * Get the information for a specific master
     *
     * @param string $name
     *
     * @return mixed
     * @throws RedisException
     */
    public function master($name)
    {
        return $this->client->execute('sentinel', ['master', $name]);
    }

    /**
     * Get the hostname and port for a specific master
     *
     * @param string $name
     *
     * @return mixed
     * @throws RedisException
     */
    public function getMasterAddressByName($name)
    {
        return $this->client->execute('sentinel', ['get-master-addr-by-name', $name]);
    }

    /**
     * Check if the Sentinel is still responding
     *
     * @return mixed
     * @throws RedisException
     */
    public function ping()
    {
        return $this->client->execute('ping');
    }

    /**
     * Perform an auto-failover which will re-elect another master and make the current master a slave
     *
     * @param string $name
     *
     * @return mixed
     * @throws RedisException
     */
    public function failover($name)
    {
        return $this->client->execute('sentinel', ['failover', $name]);
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->client->getHost();
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->client->getPort();
    }
}
