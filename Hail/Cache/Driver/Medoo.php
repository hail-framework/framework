<?php
namespace Hail\Cache\Driver;

use Hail\Cache\Driver;
use Hail\DB\Medoo as DB;
use Hail\Utils\Serialize;

/**
 * Medoo DB cache provider.
 *
 * @author FlyingHail <flyinghail@msn.com>
 */
class Medoo extends Driver
{
	/**
	 * @var Redis|null
	 */
	private $db;
	private $schema;

	public function __construct($params)
	{
		$this->db = new DB($params);

		$this->schema = [
			'table' => 'cache',
			'key' => 'id',
			'value' => 'data',
			'expire' => 'expire'
		];

		if (isset($params['schema']) && is_array($params['schema'])) {
			$this->schema = array_merge($this->schema, $params['schema']);
		}
		parent::__construct($params);
	}

	/**
	 * Gets the redis instance used by the cache.
	 *
	 * @return Redis|null
	 */
	public function getDB()
	{
		return $this->db;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		$data = $this->db->get([
			'SELECT' => [$this->schema['value'], $this->schema['expire']],
			'FROM' => $this->schema['table'],
			'WHERE' => [$this->schema['key'] => $id]
		]);

		if ($data[$this->schema['expire']] > 0 && $data[$this->schema['expire']] < NOW) {
			return false;
		}

		return Serialize::decode($data[$this->schema['value']]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
	{
		$expire = $lifetime > 0 ? NOW + $lifetime : 0;

		$data = [];
		foreach ($keysAndValues as $k => $v) {
			$data[] = [
				$this->schema['key'] => $k,
				$this->schema['value'] => Serialize::encode($v),
				$this->schema['expire'] => $expire
			];
		}
		return $this->db->multiInsert($this->schema['table'], $data, 'REPLACE');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetchMultiple(array $keys)
	{
		$data = $this->db->get([
			'SELECT' => [$this->schema['key'], $this->schema['value'], $this->schema['expire']],
			'FROM' => $this->schema['table'],
			'WHERE' => [$this->schema['key'] => $keys]
		]);

		$foundItems = [];
		foreach ($data as $v) {
			if ($data[$this->schema['expire']] > 0 && $data[$this->schema['expire']] < NOW) {
				continue;
			}

			$foundItems[$v[$this->schema['key']]] = Serialize::decode($v[$this->schema['value']]);
		}

		return $foundItems;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		return $this->db->has([
			'FROM' => $this->schema['table'],
			'WHERE' => [
				'AND' => [
					$this->schema['key'] => $id,
					'OR' => [
						[$this->schema['expire'] => 0],
						[$this->schema['expire'] . '[>]' => NOW],
					]
				]

			]
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifetime = 0)
	{
		$expire = $lifetime > 0 ? NOW + $lifetime : 0;

		$data = [
			$this->schema['key'] => $id,
			$this->schema['value'] => Serialize::encode($data),
			$this->schema['expire'] => $expire
		];

		return $this->db->insert($this->schema['table'], $data, 'REPLACE');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		return $this->db->delete($this->schema['table'], [$this->schema['key'] => $id]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		return $this->db->truncate($this->schema['table']);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		return [
			Driver::STATS_HITS => null,
			Driver::STATS_MISSES => null,
			Driver::STATS_UPTIME => null,
			Driver::STATS_MEMORY_USAGE => null,
			Driver::STATS_MEMORY_AVAILABLE => null,
		];
	}
}
