<?php

namespace Hail\Cache\Simple;

use Hail\Factory\Database as DatabaseFactory;
use Hail\Util\Serialize;

/**
 * Database cache provider.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class Database extends AbstractAdapter
{
	/**
	 * @var \Hail\Database\Database
	 */
	private $db;
	private $schema;

	public function __construct($params)
	{
		$this->schema = [
			'table' => 'cache',
			'key' => 'id',
			'value' => 'data',
			'expire' => 'expire',
		];

		if (isset($params['schema']) && \is_array($params['schema'])) {
			$this->schema = \array_merge($this->schema, $params['schema']);
		}

		$config = [
			'ttl' => $params['ttl'] ?? 0,
			'namespace' => $params['namespace'] ?? '',
		];

		unset(
			$params['schema'],
			$params['ttl'],
			$params['namespace']
		);

		$this->db = DatabaseFactory::pdo($params);

		if ($this->db->getType() === 'sqlite') {
			[
				'table' => $table,
				'key' => $keyField,
				'value' => $valueField,
				'expire' => $expireField,
			] = $this->schema;

			$this->db->exec("CREATE TABLE IF NOT EXISTS {$table} ($keyField TEXT PRIMARY KEY NOT NULL, $valueField BLOB, $expireField INTEGER)");
		}

		parent::__construct($config);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		[
			'table' => $table,
			'key' => $keyField,
			'value' => $valueField,
			'expire' => $expireField,
		] = $this->schema;

		$data = $this->db->get([
			'SELECT' => [$valueField, $expireField],
			'FROM' => $table,
			'WHERE' => [$keyField => $key],
		]);

		if (!isset($data[$expireField]) || $data[$expireField] < \time()) {
			if (!empty($data)) {
				$this->doDelete($key);
			}

			return null;
		}

		return Serialize::decode($data[$valueField]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		[
			'table' => $table,
			'key' => $keyField,
			'value' => $valueField,
			'expire' => $expireField,
		] = $this->schema;

		$expire = $ttl > 0 ? \time() + $ttl : 0;

		$data = [];
		foreach ($values as $k => $v) {
			$data[] = [
				$keyField => $k,
				$valueField => Serialize::encode($v),
				$expireField => $expire,
			];
		}

		$return = $this->db->insert($table, $data, 'REPLACE');

		return $return !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetMultiple(array $keys)
	{
		[
			'table' => $table,
			'key' => $keyField,
			'value' => $valueField,
			'expire' => $expireField,
		] = $this->schema;

		$data = $this->db->get([
			'SELECT' => [$keyField, $valueField, $expireField],
			'FROM' => $table,
			'WHERE' => [$keyField => $keys],
		]);

		$foundItems = [];
		foreach ($data as $v) {
			if ($data[$expireField] > 0 && $data[$expireField] < \time()) {
				continue;
			}

			$foundItems[$v[$keyField]] = Serialize::decode($v[$valueField]);
		}

		return $foundItems;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		[
			'table' => $table,
			'key' => $keyField,
			'expire' => $expireField,
		] = $this->schema;

		return $this->db->has([
			'FROM' => $table,
			'WHERE' => [
				'AND' => [
					$keyField => $key,
					'OR' => [
						[$expireField => 0],
						[$expireField . '[>]' => \time()],
					],
				],

			],
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		[
			'table' => $table,
			'key' => $keyField,
			'value' => $valueField,
			'expire' => $expireField,
		] = $this->schema;

		$expire = $ttl > 0 ? \time() + $ttl : 0;

		$data = [
			$keyField => $key,
			$valueField => Serialize::encode($value),
			$expireField => $expire,
		];

		$return = $this->db->insert($table, $data, 'REPLACE');

		return $return !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		[
			'table' => $table,
			'key' => $keyField,
		] = $this->schema;

		return $this->db->delete($table, [$keyField => $key]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		return $this->db->truncate($this->schema['table']);
	}
}
