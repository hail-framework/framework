<?php
/*!
 * Medoo database framework
 * http://medoo.in
 * Version 1.2
 *
 * Copyright 2017, Angel Lai
 * Released under the MIT license
 */

namespace Hail\Database;

use PDO;
use Hail\Facades\Json;


/**
 * From Medoo, not include data map.
 *
 * @package Hail\Database
 * @author  Hao Feng <flyinghail@msn.com>
 */
class Database
{
	// General
	protected $type;
	protected $database;

	// Optional
	protected $prefix = '';

	protected $option = [];

	// use php-cp extension
	protected $connectPool = false;

	/** @var Event */
	protected $event;

	/**
	 * @var PDO $pdo
	 */
	protected $pdo;

	public function __construct(array $options)
	{
		$this->connect($options);
	}

	/**
	 * @param array $options
	 *
	 * @return static
	 */
	public function connect(array $options = [])
	{
		if (isset($options['type'])) {
			$this->type = strtolower($options['type']);
		}

		if (isset($options['prefix'])) {
			$this->prefix = $options['prefix'];
		}

		if (isset($options['option'])) {
			$this->option = $options['option'];
		}

		if (isset($options['connectPool']) && $options['connectPool']) {
			$this->connectPool = class_exists('\pdoProxy');
		}

		$commands = [];
		if (isset($options['command']) && is_array($options['command'])) {
			$commands = $options['command'];
		}

		if (isset($options['dsn'])) {
			if (isset($options['dsn']['driver'])) {
				$attr = $options['dsn'];

				if ($this->type === null) {
					$this->type = $attr['driver'];
				}
			} else {
				return null;
			}
		} else {
			$port = null;
			if (isset($options['port'])) {
				$port = ((int) $options['port']) ?: null;
			}

			$attr = [
				'driver' => $this->type,
				'host' => $options['server'] ?? null,
				'dbname' => $options['database'],
				'port' => $port,
			];

			switch ($this->type) {
				case 'mariadb':
					$attr['type'] = 'mysql';
				case 'mysql':
					if (isset($options['socket'])) {
						$attr['unix_socket'] = $options['socket'];
						unset($attr['host'], $attr['port']);
					}

					// Make MySQL using standard quoted identifier
					$commands[] = 'SET SQL_MODE=ANSI_QUOTES';
					break;

				case 'pgsql':
					break;

				case 'sybase':
					$attr['driver'] = 'dblib';
					break;

				case 'oracle':
					$attr['driver'] = $attr['oci'];
					if ($attr['host']) {
						$attr['dbname'] = '//' .$attr['host'] . ':' . ($attr['port'] ??  '1521') . '/' . $attr['dbname'];

					}
					unset($attr['host'], $attr['port']);

					if (isset($options['charset'])) {
						$attr['charset'] = $options['charset'];
					}
					break;

				case 'mssql':
					$attr['driver'] = strpos(PHP_OS, 'WIN') !== false ? 'sqlsrv' : 'dblib';

					// Keep MSSQL QUOTED_IDENTIFIER is ON for standard quoting
					$commands[] = 'SET QUOTED_IDENTIFIER ON';
					// Make ANSI_NULLS is ON for NULL value
					$commands[] = 'SET ANSI_NULLS ON';
					break;

				case 'sqlite':
					$this->pdo = new PDO('sqlite:' . $options['file'], null, null, $this->option);
					$this->database = $options['file'];

					return $this;
			}
		}

		$this->database = $options['database'] ?? $attr['dbname'];
		$driver = $attr['driver'];
		unset($attr['driver']);


		$stack = [];
		foreach ($attr as $key => $value) {
			if ($value === null) {
				continue;
			}

			if (is_int($key)) {
				$stack[] = $value;
			} else {
				$stack[] = $key . '=' . $value;
			}
		}

		$dsn = $driver . ':' . implode($stack, ';');

		if (
			isset($options['charset']) &&
			in_array($this->type, ['mariadb', 'mysql', 'pgsql', 'sybase', 'mssql'], true)

		) {
			$commands[] = "SET NAMES '{$options['charset']}'";
		}

		$this->event('start', Event::CONNECT);

		$class = $this->connectPool ? '\pdoProxy' : 'PDO';
		$this->pdo = new $class(
			$dsn,
			$options['username'],
			$options['password'],
			$this->option
		);

		$this->event('done');

		foreach ($commands as $value) {
			$this->release(
				$this->exec($value)
			);
		}

		return $this;
	}

	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param $query
	 *
	 * @return bool|\PDOStatement
	 */
	public function query($query)
	{
		if (PRODUCTION_MODE || strpos($query, 'EXPLAIN') === 0) {
			return $this->pdo->query($query);
		}

		$this->event('sql', $query);
		$query = $this->pdo->query($query);
		$this->event('query');

		return $query;
	}

	/**
	 * @param $query
	 *
	 * @return bool|int
	 */
	public function exec($query)
	{
		if (PRODUCTION_MODE) {
			return $this->pdo->exec($query);
		}

		$this->event('sql', $query);
		$return = $this->pdo->exec($query);
		$this->event('query');

		return $return;
	}

	/**
	 * @param mixed $result
	 *
	 * @return mixed
	 */
	public function release($result = null)
	{
		if (!PRODUCTION_MODE) {
			if ($result === false &&
				($error = $this->pdo->errorInfo()) &&
				isset($error[0])
			) {
				$result = $error;
				$this->event('error');
			}

			$this->event('done', $result);
		}

		if ($this->connectPool) {
			$this->pdo->release();
		}

		return $result;
	}

	/**
	 * @param $string
	 *
	 * @return string
	 */
	public function quote($string)
	{
		return $this->pdo->quote($string);
	}

	protected function tableQuote($table)
	{
		if (strpos($table, '.') !== false) { // database.table
			return '"' . str_replace('.', '"."' . $this->prefix, $table) . '"';
		}

		return '"' . $this->prefix . $table . '"';
	}

	protected function columnQuote($string)
	{
		if (strpos($string, '#') === 0) {
			$string = substr($string, 1);
		}

		if ($string === '*') {
			return '*';
		}

		if (($p = strpos($string, '.')) !== false) { // table.column
			if ($string[$p + 1] === '*') {// table.*
				return $this->tableQuote(substr($string, 0, $p)) . '.*';
			}

			return '"' . $this->prefix . str_replace('.', '"."', $string) . '"';
		}

		return '"' . $string . '"';
	}

	protected function columnPush($columns)
	{
		if ($columns === '*') {
			return $columns;
		}

		if (is_string($columns)) {
			$columns = [$columns];
		}

		$stack = [];
		foreach ($columns as $key => $value) {
			preg_match('/([a-zA-Z0-9_\-\.]*)\s*\(([a-zA-Z0-9_\-]*)\)/i', $value, $match);

			$special = is_int($key) ? false :
				in_array($key, ['COUNT', 'MAX', 'MIN', 'SUM', 'AVG', 'ROUND'], true);

			if (isset($match[1], $match[2])) {
				$value = $this->columnQuote($match[1]);
				$stack[] = ($special ? $key . '(' . $value . ')' : $value) . ' AS ' . $this->columnQuote($match[2]);
			} else {
				$value = $this->columnQuote($value);
				$stack[] = $special ? $key . '(' . $value . ')' : $value;
			}
		}

		return implode(',', $stack);
	}


	protected function arrayQuote($array)
	{
		$temp = [];

		foreach ($array as $value) {
			$temp[] = is_int($value) ? $value : $this->pdo->quote($value);
		}

		return implode(',', $temp);
	}

	protected function innerConjunct($data, $conjunctor, $outerConjunctor)
	{
		$haystack = [];
		foreach ($data as $value) {
			$haystack[] = '(' . $this->dataImplode($value, $conjunctor) . ')';
		}

		return implode($outerConjunctor . ' ', $haystack);
	}

	protected function fnQuote($column, $string)
	{
		return (strpos($column, '#') === 0 && preg_match('/^[A-Z0-9\_]*\([^)]*\)$/', $string)) ? $string : $this->quote($string);
	}

	protected function quoteValue($column, $value)
	{
		switch (gettype($value)) {
			case 'NULL':
				return 'NULL';

			case 'array':
				return $this->quote(Json::encode($value));

			case 'boolean':
				return $value ? '1' : '0';
				break;

			case 'integer':
			case 'double':
				return $value;

			case 'string':
				return $this->fnQuote($column, $value);
		}
	}

	protected function dataImplode($data, $conjunctor)
	{
		$wheres = [];

		foreach ($data as $key => $value) {
			$type = gettype($value);
			if (
				$type === 'array' &&
				preg_match("/^(AND|OR)(\s+#.*)?$/i", $key, $relation)
			) {
				$wheres[] = 0 !== count(array_diff_key($value, array_keys(array_keys($value)))) ?
					'(' . $this->dataImplode($value, ' ' . $relation[1]) . ')' :
					'(' . $this->innerConjunct($value, ' ' . $relation[1], $conjunctor) . ')';
			} elseif (
				is_int($key) &&
				preg_match('/([\w\.\-]+)\[(\>|\>\=|\<|\<\=|\!|\=)\]([\w\.\-]+)/i', $value, $match)
			) {
				$operator = $match[2];

				$wheres[] = $this->columnQuote($match[1]) . ' ' . $operator . ' ' . $this->columnQuote($match[3]);
			} else {
				preg_match('/(#?)([\w\.\-]+)(\[(\>|\>\=|\<|\<\=|\!|\<\>|\>\<|\!?~)\])?/i', $key, $match);
				$column = $this->columnQuote($match[2]);

				if (isset($match[4])) {
					$operator = $match[4];

					if ($operator === '!') {
						switch ($type) {
							case 'NULL':
								$wheres[] = $column . ' IS NOT NULL';
								break;

							case 'array':
								$wheres[] = $column . ' NOT IN (' . $this->arrayQuote($value) . ')';
								break;

							case 'integer':
							case 'double':
								$wheres[] = $column . ' != ' . $value;
								break;

							case 'boolean':
								$wheres[] = $column . ' != ' . ($value ? '1' : '0');
								break;

							case 'string':
								$wheres[] = $column . ' != ' . $this->fnQuote($key, $value);
								break;
						}
					} elseif ($operator === '<>' || $operator === '><') {
						if ($type === 'array') {
							if ($operator === '><') {
								$column .= ' NOT';
							}

							if (is_numeric($value[0]) && is_numeric($value[1])) {
								$wheres[] = '(' . $column . ' BETWEEN ' . $value[0] . ' AND ' . $value[1] . ')';
							} else {
								$wheres[] = '(' . $column . ' BETWEEN ' . $this->quote($value[0]) . ' AND ' . $this->quote($value[1]) . ')';
							}
						}
					} elseif ($operator === '~' || $operator === '!~') {
						if ($type !== 'array') {
							$value = [$value];
						}

						$connector = ' OR ';
						$stack = array_values($value);

						if (is_array($stack[0])) {
							if (isset($value['AND']) || isset($value['OR'])) {
								$connector = ' ' . array_keys($value)[0] . ' ';
								$value = $stack[0];
							}
						}

						$like = [];

						foreach ($value as $item) {
							$item = (string) $item;

							if (!preg_match('/(\[.+\]|_|%.+|.+%)/', $item)) {
								$item = '%' . $item . '%';
							}

							$like[] = $column . ($operator === '!~' ? ' NOT' : '') . ' LIKE ' . $this->fnQuote($key, $item);
						}

						$wheres[] = '(' . implode($connector, $like) . ')';
					} elseif (in_array($operator, ['>', '>=', '<', '<='], true)) {
						$condition = $column . ' ' . $operator . ' ';
						if (is_numeric($value)) {
							$condition .= $value;
						} elseif (strpos($key, '#') === 0) {
							$condition .= $this->fnQuote($key, $value);
						} else {
							$condition .= $this->quote($value);
						}

						$wheres[] = $condition;
					}
				} else {
					switch ($type) {
						case 'NULL':
							$wheres[] = $column . ' IS NULL';
							break;

						case 'array':
							$wheres[] = $column . ' IN (' . $this->arrayQuote($value) . ')';
							break;

						case 'integer':
						case 'double':
							$wheres[] = $column . ' = ' . $value;
							break;

						case 'boolean':
							$wheres[] = $column . ' = ' . ($value ? '1' : '0');
							break;

						case 'string':
							$wheres[] = $column . ' = ' . $this->fnQuote($key, $value);
							break;
					}
				}
			}
		}

		return implode($conjunctor . ' ', $wheres);
	}

	protected function suffixClause($struct)
	{
		$where = $struct['WHERE'] ?? [];
		foreach (['GROUP', 'ORDER', 'LIMIT', 'HAVING'] as $v) {
			if (isset($struct[$v]) && !isset($where[$v])) {
				$where[$v] = $struct[$v];
			}
		}

		return $this->whereClause($where);
	}

	protected function whereClause($where)
	{
		if (empty($where)) {
			return '';
		}

		$clause = '';
		if (is_array($where)) {
			$whereKeys = array_keys($where);
			$whereAND = preg_grep("/^AND\s*#?$/i", $whereKeys);
			$whereOR = preg_grep("/^OR\s*#?$/i", $whereKeys);

			$single_condition = array_diff_key($where,
				array_flip(['AND', 'OR', 'GROUP', 'ORDER', 'HAVING', 'LIMIT', 'LIKE', 'MATCH'])
			);

			if ($single_condition !== []) {
				$condition = $this->dataImplode($single_condition, ' AND');
				if ($condition !== '') {
					$clause = ' WHERE ' . $condition;
				}
			}

			if (!empty($whereAND)) {
				$value = array_values($whereAND);
				$clause = ' WHERE ' . $this->dataImplode($where[$value[0]], ' AND');
			}

			if (!empty($whereOR)) {
				$value = array_values($whereOR);
				$clause = ' WHERE ' . $this->dataImplode($where[$value[0]], ' OR');
			}

			if (isset($where['MATCH'])) {
				$MATCH = $where['MATCH'];

				if (is_array($MATCH) && isset($MATCH['columns'], $MATCH['keyword'])) {
					$columns = str_replace('.', '"."', implode('", "', $MATCH['columns']));
					$keywords = $this->quote($MATCH['keyword']);

					$clause .= ($clause !== '' ? ' AND ' : ' WHERE ') . ' MATCH ("' . $columns . '") AGAINST (' . $keywords . ')';
				}
			}

			if (isset($where['GROUP'])) {
				$clause .= ' GROUP BY ' . implode(', ', array_map([$this, 'columnQuote'], (array) $where['GROUP']));

				if (isset($where['HAVING'])) {
					$clause .= ' HAVING ' . $this->dataImplode($where['HAVING'], ' AND');
				}
			}

			if (isset($where['ORDER'])) {
				$rsort = '/(^[a-zA-Z0-9_\-\.]*)(\s*(DESC|ASC))?/';
				$ORDER = $where['ORDER'];

				if (is_array($ORDER)) {
					$stack = [];

					foreach ($ORDER as $column => $value) {
						if (is_array($value)) {
							$stack[] = 'FIELD(' . $this->columnQuote($column) . ', ' . $this->arrayQuote($value) . ')';
						} else if ($value === 'ASC' || $value === 'DESC') {
							$stack[] = $this->columnQuote($column) . ' ' . $value;
						} else if ($value === 'asc' || $value === 'desc') {
							$stack[] = $this->columnQuote($column) . ' ' . strtoupper($value);
						} else if (is_int($column)) {
							preg_match($rsort, $value, $match);
							$stack[] = $this->columnQuote($match[1]) . ' ' . ($match[3] ?? '');
						}
					}

					$clause .= ' ORDER BY ' . implode($stack, ',');
				} else {
					preg_match($rsort, $ORDER, $match);
					$clause .= ' ORDER BY ' . $this->columnQuote($match[1]) . ' ' . ($match[3] ?? '');
				}
			}

			if (isset($where['LIMIT'])) {
				$LIMIT = $where['LIMIT'];

				if (is_numeric($LIMIT)) {
					$clause .= ' LIMIT ' . $LIMIT;
				} else if (
					is_array($LIMIT) &&
					is_numeric($LIMIT[0]) &&
					is_numeric($LIMIT[1])
				) {
					if ($this->type === 'pgsql') {
						$clause .= ' OFFSET ' . $LIMIT[0] . ' LIMIT ' . $LIMIT[1];
					} else {
						$clause .= ' LIMIT ' . $LIMIT[0] . ',' . $LIMIT[1];
					}
				}
			}
		} else if ($where !== null) {
			$clause .= ' ' . $where;
		}

		return $clause;
	}

	protected function getTable($struct)
	{
		return $struct['TABLE'] ?? $struct['FROM'] ?? $struct['SELECT'];
	}

	protected function getColumns($struct)
	{
		if (isset($struct['COLUMNS'])) {
			return $struct['COLUMNS'];
		} else if (
			isset($struct['TABLE'], $struct['SELECT']) ||
			isset($struct['FROM'], $struct['SELECT'])
		) {
			return $struct['SELECT'];
		}

		return '*';
	}

	protected function selectContext($struct)
	{
		if (is_string($struct)) {
			$struct = [
				'TABLE' => $struct,
			];
		}

		$table = $this->getTable($struct);
		preg_match('/([a-zA-Z0-9_\-]*)\s*\(([a-zA-Z0-9_\-]*)\)/i', $table, $tableMatch);

		if (isset($tableMatch[1], $tableMatch[2])) {
			$table = $this->tableQuote($tableMatch[1]);
			$tableQuery = $table . ' AS ' . $this->tableQuote($tableMatch[2]);
		} else {
			$table = $this->tableQuote($table);
			$tableQuery = $table;
		}

		if (isset($struct['JOIN'])) {
			$join = [];
			$joinSign = [
				'>' => 'LEFT',
				'<' => 'RIGHT',
				'<>' => 'FULL',
				'><' => 'INNER',
			];

			foreach ($struct['JOIN'] as $sub => $relation) {
				preg_match('/(\[(\<|\>|\>\<|\<\>)\])?([a-zA-Z0-9_\-]*)\s?(\(([a-zA-Z0-9_\-]*)\))?/', $sub, $match);

				if ($match[2] != '' && $match[3] != '') {
					if (is_string($relation)) {
						$relation = 'USING ("' . $relation . '")';
					} else if (is_array($relation)) {
						// For ['column1', 'column2']
						if (isset($relation[0])) {
							$relation = 'USING ("' . implode('", "', $relation) . '")';
						} else {
							$joins = [];

							foreach ($relation as $key => $value) {
								$joins[] = (
									strpos($key, '.') > 0 ?
										// For ['tableB.column' => 'column']
										'"' . $this->prefix . str_replace('.', '"."', $key) . '"' :

										// For ['column1' => 'column2']
										$table . '."' . $key . '"'
									) .
									' = ' .
									$this->tableQuote(isset($match[5]) ? $match[5] : $match[3]) . '."' . $value . '"';
							}

							$relation = 'ON ' . implode(' AND ', $joins);
						}
					}

					$tableName = $this->tableQuote($match[3]) . ' ';
					if (isset($match[5])) {
						$tableName .= 'AS ' . $this->tableQuote($match[5]) . ' ';
					}

					$join[] = $joinSign[$match[2]] . ' JOIN ' . $tableName . $relation;
				}
			}

			$tableQuery .= ' ' . implode(' ', $join);
		}

		$columns = $this->getColumns($struct);
		if (isset($struct['FUN'])) {
			$fn = $struct['FUN'];
			if ($fn == 1) {
				$column = '1';
			} else {
				$column = $fn . '(' . $this->columnPush($columns) . ')';
			}
		} else {
			$column = $this->columnPush($columns);
		}

		return 'SELECT ' . $column . ' FROM ' . $tableQuery . $this->suffixClause($struct);
	}

	/**
	 * @param $table
	 *
	 * @return array
	 */
	public function headers($table)
	{
		$this->event('start', Event::SELECT);
		$sth = $this->query('SELECT * FROM ' . $this->tableQuote($table));

		$headers = [];
		for ($i = 0, $n = $sth->columnCount(); $i < $n; ++$i) {
			$headers[] = $sth->getColumnMeta($i);
		}

		return $this->release($headers);
	}

	/**
	 * @param       $struct
	 * @param int   $fetch
	 * @param mixed $fetchArgs
	 *
	 * @return array|bool
	 */
	public function select($struct, $fetch = PDO::FETCH_ASSOC, $fetchArgs = null)
	{
		$this->event('start', Event::SELECT);
		$query = $this->query(
			$this->selectContext($struct)
		);

		$return = false;
		if ($query) {
			if ($fetchArgs !== null) {
				$return = $query->fetchAll($fetch, $fetchArgs);
			} else {
				$return = $query->fetchAll($fetch);
			}
		}

		return $this->release($return);
	}

	protected function insertContext($table, $datas, $INSERT, $multi = false)
	{
		if (is_array($table)) {
			$datas = $table['VALUES'] ?? $table['SET'];
			$table = $table['FROM'] ?? $table['TABLE'] ?? $table['INSERT'];

			if (is_string($datas)) {
				$INSERT = $datas;
			}
		}

		if (strpos($INSERT, ' ') !== false) {
			$INSERT = explode(' ', trim($INSERT));
			if (count($INSERT) > 3) {
				$INSERT = 'INSERT';
			} else {
				if ($INSERT[0] !== 'REPLACE') {
					$INSERT[0] = 'INSERT';
				}

				if (isset($INSERT[1]) &&
					!in_array($INSERT[1], ['LOW_PRIORITY', 'DELAYED', 'IGNORE'], true)
				) {
					$INSERT[1] = '';
				}

				if (isset($INSERT[2]) &&
					($INSERT[1] === $INSERT[2] || $INSERT[2] !== 'IGNORE')
				) {
					$INSERT[2] = '';
				}

				$INSERT = trim(implode(' ', $INSERT));
			}
		} else if ($INSERT !== 'REPLACE') {
			$INSERT = 'INSERT';
		}

		// Check indexed or associative array
		if (!isset($datas[0])) {
			$datas = [$datas];
		}

		if ($multi) {
			$columns = array_map(
				[$this, 'columnQuote'],
				array_keys($datas[0])
			);

			$values = [];
			foreach ($datas as $data) {
				$sub = [];
				foreach ($data as $key => $value) {
					$sub[] = $this->quoteValue($key, $value);
				}
				$values[] = '(' . implode(', ', $sub) . ')';
			}

			$sql = $INSERT . ' INTO ' . $this->tableQuote($table) . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $values);
		} else {
			$sql = [];
			foreach ($datas as $data) {
				$values = [];
				$columns = [];

				foreach ($data as $key => $value) {
					$columns[] = $this->columnQuote($key);
					$values[] = $this->quoteValue($key, $value);
				}

				$sql[] = $INSERT . ' INTO ' . $this->tableQuote($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
			}
		}

		return $sql;
	}

	/**
	 * @param        $table
	 * @param array  $datas
	 * @param string $INSERT
	 *
	 * @return array|mixed
	 */
	public function insert($table, $datas = [], $INSERT = 'INSERT')
	{
		$this->event('start', Event::INSERT);
		$sql = $this->insertContext($table, $datas, $INSERT, true);

		return $this->release(
			$this->exec($sql)
		);
	}

	/**
	 * @return string|array
	 */
	public function lastInsertId()
	{
		if ($this->type === 'oracle') {
			return 0;
		} elseif ($this->type === 'mssql') {
			return $this->pdo->query('SELECT SCOPE_IDENTITY()')->fetchColumn();
		}

		return $this->pdo->lastInsertId();
	}

	/**
	 * @param        $table
	 * @param array  $datas
	 * @param string $INSERT
	 *
	 * @return bool|int
	 * @deprecated
	 */
	public function multiInsert($table, $datas = [], $INSERT = 'INSERT')
	{
		return $this->insert($table, $datas, $INSERT);
	}

	/**
	 * @param       $table
	 * @param array $data
	 * @param null  $where
	 *
	 * @return bool|int
	 */
	public function update($table, $data = [], $where = null)
	{
		$this->event('start', Event::UPDATE);
		if (is_array($table)) {
			$data = $table['SET'] ?? $table['VALUES'];
			$where = $this->suffixClause($table);
			$table = $table['FROM'] ?? $table['TABLE'] ?? $table['UPDATE'];
		} else {
			$where = $this->whereClause($where);
		}

		$fields = [];

		foreach ($data as $key => $value) {
			preg_match('/([\w]+)(\[(\+|\-|\*|\/)\])?/i', $key, $match);

			if (isset($match[3])) {
				if (is_numeric($value)) {
					$fields[] = $this->columnQuote($match[1]) . ' = ' . $this->columnQuote($match[1]) . ' ' . $match[3] . ' ' . $value;
				}
			} else {
				$column = $this->columnQuote($key);
				$fields[] = $column . ' = ' . $this->quoteValue($key, $value);
			}
		}

		return $this->release(
			$this->exec('UPDATE ' . $this->tableQuote($table) . ' SET ' . implode(', ', $fields) . $where)
		);
	}

	/**
	 * @param      $table
	 * @param null $where
	 *
	 * @return bool|int
	 */
	public function delete($table, $where = null)
	{
		$this->event('start', Event::DELETE);
		if (is_array($table)) {
			$where = $this->suffixClause($table);
			$table = $table['FROM'] ?? $table['TABLE'] ?? $table['DELETE'];
		} else {
			$where = $this->whereClause($where);
		}

		return $this->release(
			$this->exec('DELETE FROM ' . $this->tableQuote($table) . $where)
		);
	}

	/**
	 * @param                   $table
	 * @param                   $columns
	 * @param string|array|null $search
	 * @param mixed             $replace
	 * @param array|null        $where
	 *
	 * @return bool|int
	 */
	public function replace($table, $columns, $search = null, $replace = null, $where = null)
	{
		$this->event('start', Event::UPDATE);
		if (is_array($columns)) {
			$replace_query = [];

			foreach ($columns as $column => $replacements) {
				foreach ($replacements as $k => $v) {
					$replace_query[] = $column . ' = REPLACE(' . $this->columnQuote($column) . ', ' . $this->quote($k) . ', ' . $this->quote($v) . ')';
				}
			}

			$replace_query = implode(', ', $replace_query);
			$where = $search;
		} else if (is_array($search)) {
			$replace_query = [];
			foreach ($search as $k => $v) {
				$replace_query[] = $columns . ' = REPLACE(' . $this->columnQuote($columns) . ', ' . $this->quote($k) . ', ' . $this->quote($v) . ')';
			}
			$replace_query = implode(', ', $replace_query);
			$where = $replace;
		} else {
			$replace_query = $columns . ' = REPLACE(' . $this->columnQuote($columns) . ', ' . $this->quote($search) . ', ' . $this->quote($replace) . ')';
		}

		return $this->release(
			$this->exec('UPDATE ' . $this->tableQuote($table) . ' SET ' . $replace_query . $this->whereClause($where))
		);
	}

	/**
	 * @param array          $struct
	 * @param int            $fetch
	 * @param int|array|null $fetchArgs
	 *
	 * @return array|bool
	 */
	public function get($struct, $fetch = PDO::FETCH_ASSOC, $fetchArgs = null)
	{
		$this->event('start', Event::SELECT);
		$query = $this->query(
			$this->selectContext($struct) . ' LIMIT 1'
		);

		$return = false;
		if ($query) {
			if ($fetchArgs === null) {
				$return = $query->fetch($fetch);
			} else {
				$fetchArgs = (array) $fetchArgs;
				array_unshift($fetchArgs, $fetch);

				switch (count($fetchArgs)) {
					case 1:
						$query->setFetchMode($fetchArgs[0]);
						break;
					case 2:
						$query->setFetchMode($fetchArgs[0], $fetchArgs[1]);
						break;
					case 3:
						$query->setFetchMode($fetchArgs[0], $fetchArgs[1], $fetchArgs[2]);
						break;
				}

				$return = $query->fetch($fetch);
			}

			if (is_array($return) && !empty($return)) {
				if (isset($struct['FROM']) || isset($struct['TABLE'])) {
					$column = $struct['SELECT'] ?? $struct['COLUMNS'] ?? null;
				} else {
					$column = $struct['COLUMNS'] ?? null;
				}

				if (is_string($column) && $column !== '*') {
					$return = $return[$column];
				}
			}
		}

		return $this->release($return);
	}

	/**
	 * @param array $struct
	 *
	 * @return bool
	 */
	public function has(array $struct)
	{
		$this->event('start', Event::SELECT);
		if (isset($struct['COLUMNS']) || isset($struct['SELECT'])) {
			unset($struct['COLUMNS'], $struct['SELECT']);
		}
		$struct['FUN'] = 1;

		$query = $this->query(
			'SELECT EXISTS(' . $this->selectContext($struct) . ')'
		);

		$return = false;
		if ($query) {
			$return = $query->fetchColumn();
		}

		return $this->release($return) === '1';
	}

	/**
	 * @param array $struct
	 *
	 * @return bool|int
	 */
	public function count(array $struct)
	{
		$this->event('start', Event::SELECT);
		$struct['FUN'] = 'COUNT';
		$query = $this->query(
			$this->selectContext($struct)
		);

		$return = false;
		if ($query) {
			$return = (int) $query->fetchColumn();
		}

		return $this->release($return);
	}

	/**
	 * @param array $struct
	 *
	 * @return bool|int|string
	 */
	public function max(array $struct)
	{
		$this->event('start', Event::SELECT);
		$struct['FUN'] = 'MAX';
		$query = $this->query(
			$this->selectContext($struct)
		);

		$return = false;
		if ($query) {
			$max = $query->fetchColumn();
			$return = is_numeric($max) ? (int) $max : $max;
		}

		return $this->release($return);
	}

	/**
	 * @param array $struct
	 *
	 * @return bool|int|string
	 */
	public function min(array $struct)
	{
		$this->event('start', Event::SELECT);
		$struct['FUN'] = 'MIN';
		$query = $this->query(
			$this->selectContext($struct)
		);

		$return = false;
		if ($query) {
			$min = $query->fetchColumn();
			$return = is_numeric($min) ? (int) $min : $min;
		}

		return $this->release($return);
	}

	/**
	 * @param array $struct
	 *
	 * @return bool|int
	 */
	public function avg(array $struct)
	{
		$this->event('start', Event::SELECT);
		$struct['FUN'] = 'AVG';
		$query = $this->query(
			$this->selectContext($struct)
		);

		$return = false;
		if ($query) {
			$return = (int) $query->fetchColumn();
		}

		return $this->release($return);
	}

	/**
	 * @param array $struct
	 *
	 * @return bool|int
	 */
	public function sum(array $struct)
	{
		$this->event('start', Event::SELECT);
		$struct['FUN'] = 'SUM';
		$query = $this->query(
			$this->selectContext($struct)
		);

		$return = false;
		if ($query) {
			$return = (int) $query->fetchColumn();
		}

		return $this->release($return);
	}

	/**
	 * @param $table
	 *
	 * @return bool|int
	 */
	public function truncate($table)
	{
		return $this->release(
			$this->exec(
				'TRUNCATE TABLE ' . $table
			)
		);
	}

	/**
	 * @param $actions
	 *
	 * @return bool
	 */
	public function action($actions)
	{
		$result = false;
		if (is_callable($actions)) {
			if (PRODUCTION_MODE) {
				$this->pdo->beginTransaction();
				$result = $actions($this);
			} else {
				$event = new Event($this->type, $this->database, Event::TRANSACTION);

				$this->pdo->beginTransaction();
				$result = $actions($this);

				$event->query();
				$this->event = $event;
			}

			if ($result === false) {
				$this->pdo->rollBack();
			} else {
				$this->pdo->commit();
			}
		}

		return $this->release($result);
	}

	protected function event($type, $arg = null)
	{
		if (PRODUCTION_MODE) {
			return;
		}

		switch ($type) {
			case 'start':
				$this->event = new Event($this->type, $this->database, $arg);
				break;

			case 'sql':
				if ($this->event === null) {
					$this->event('start', Event::QUERY);
					$this->event->sql($arg, false);
				} else {
					$this->event->sql($arg);
				}
				break;

			case 'query':
				if ($this->event !== null) {
					$this->event->query();
				}
				break;

			case 'done':
				if ($this->event !== null) {
					$this->event->done($arg);
					$this->event = null;
				}
				break;

			case 'error':
				if ($this->event !== null) {
					$this->event->error();
				}
				break;
		}
	}

	public function error()
	{
		return $this->pdo->errorInfo();
	}

	public function info()
	{
		$output = [
			'server' => 'SERVER_INFO',
			'driver' => 'DRIVER_NAME',
			'client' => 'CLIENT_VERSION',
			'version' => 'SERVER_VERSION',
			'connection' => 'CONNECTION_STATUS',
		];

		foreach ($output as $key => $value) {
			$output[$key] = @$this->pdo->getAttribute(constant('PDO::ATTR_' . $value));
		}

		return $output;
	}
}
