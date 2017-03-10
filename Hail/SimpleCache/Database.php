<?php
namespace Hail\SimpleCache;

use Hail\Database\Database as DB;
use Hail\Facades\Serialize;

/**
 * Database cache provider.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class Database extends AbstractAdapter
{
	/**
	 * @var DB|null
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

		if ($this->db->getType() === 'sqlite') {
			$table = $this->schema['table'];
			$id = $this->schema['id'];
			$data = $this->schema['data'];
			$exp = $this->schema['expire'];
			$this->db->exec("CREATE TABLE IF NOT EXISTS {$table} ($id TEXT PRIMARY KEY NOT NULL, $data BLOB, $exp INTEGER)");
		}

		parent::__construct($params);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		$data = $this->db->get([
			'SELECT' => [$this->schema['value'], $this->schema['expire']],
			'FROM' => $this->schema['table'],
			'WHERE' => [$this->schema['key'] => $key]
		]);

		if ($data[$this->schema['expire']] > NOW) {
			return null;
		}

		return Serialize::decode($data[$this->schema['value']]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		$expire = $ttl > 0 ? NOW + $ttl : 0;

		$data = [];
		foreach ($values as $k => $v) {
			$data[] = [
				$this->schema['key'] => $k,
				$this->schema['value'] => Serialize::encode($v),
				$this->schema['expire'] => $expire
			];
		}

		$return = $this->db->insert($this->schema['table'], $data, 'REPLACE');
		return $return !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetMultiple(array $keys)
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
	protected function doHas(string $key)
	{
		return $this->db->has([
			'FROM' => $this->schema['table'],
			'WHERE' => [
				'AND' => [
					$this->schema['key'] => $key,
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
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		$expire = $ttl > 0 ? NOW + $ttl : 0;

		$data = [
			$this->schema['key'] => $key,
			$this->schema['value'] => Serialize::encode($value),
			$this->schema['expire'] => $expire
		];

		$return = $this->db->insert($this->schema['table'], $data, 'REPLACE');

		return $return !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		return $this->db->delete($this->schema['table'], [$this->schema['key'] => $key]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		return $this->db->truncate($this->schema['table']);
	}
}
