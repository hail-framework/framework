<?php

namespace Hail\DB;

use PDO;

/*!
 * Medoo database framework
 * http://medoo.in
 * Version 1.0
 *
 * Copyright 2015, Angel Lai
 * Released under the MIT license
 * Modified by FlyingHail <flyinghail@msn.com>
 */

class Medoo
{
	// General
	protected $type;
	protected $charset;
	protected $database;

	// For MySQL, MariaDB, MSSQL, Sybase, PostgreSQL, Oracle
	protected $server;
	protected $username;
	protected $password;

	// For SQLite
	protected $file;

	// For MySQL or MariaDB with unix_socket
	protected $socket;

	// Optional
	protected $port;
	protected $prefix;
	protected $option = [];

	// Variable
	protected $logs = [];
	protected $debug = false;

	/**
	 * @var PDO $pdo
	 */
	protected $pdo;

	public function __construct(array $options)
	{
		try {
			foreach ($options as $option => $value) {
				$this->$option = $value;
			}

			if (
				isset($this->port) &&
				is_int($this->port * 1)
			) {
				$port = $this->port;
			}

			$this->type = $type = strtolower($this->type);

			$dsn = '';
			$commands = [];
			switch ($type) {
				case 'mariadb':
				case 'mysql':
					if ($this->socket) {
						$dsn = $type . ':unix_socket=' . $this->socket . ';dbname=' . $this->database;
					} else {
						$dsn = $type . ':host=' . $this->server . ';port=' . ($port ?? '3306') . ';dbname=' . $this->database;
					}

					// Make MySQL using standard quoted identifier
					$commands[] = 'SET SQL_MODE=ANSI_QUOTES';
					break;

				case 'pgsql':
					$dsn = $type . ':host=' . $this->server . ';port=' . ($port ?? '5432') . ';dbname=' . $this->database;
					break;

				case 'sybase':
					$dsn = 'dblib:host=' . $this->server . ':' . ($port ?? '5000') . ';dbname=' . $this->database;
					break;

				case 'oracle':
					$dbname = $this->server ?
						'//' . $this->server . ':' . ($port ?? '1521') . '/' . $this->database :
						$this->database;

					$dsn = 'oci:dbname=' . $dbname . ($this->charset ? ';charset=' . $this->charset : '');
					break;

				case 'mssql':
					$dsn = strstr(PHP_OS, 'WIN') ?
						'sqlsrv:server=' . $this->server . ',' . ($port ?? '1433') . ';database=' . $this->database :
						'dblib:host=' . $this->server . ':' . ($port ?? '1433') . ';dbname=' . $this->database;

					// Keep MSSQL QUOTED_IDENTIFIER is ON for standard quoting
					$commands[] = 'SET QUOTED_IDENTIFIER ON';
					break;

				case 'sqlite':
					$dsn = $type . ':' . $this->file;
					$this->username = null;
					$this->password = null;
					break;
			}

			if (
				$this->charset &&
				in_array($type, ['mariadb', 'mysql', 'pgsql', 'sybase', 'mssql'], true)

			) {
				$commands[] = "SET NAMES '" . $this->charset . "'";
			}

			$this->pdo = new PDO(
				$dsn,
				$this->username,
				$this->password,
				$this->option
			);

			foreach ($commands as $value) {
				$this->pdo->exec($value);
			}
		} catch (\PDOException $e) {
			throw new \RuntimeException($e->getMessage());
		}
	}

	public function query($query)
	{
		if ($this->debug) {
			echo $query;

			$this->debug = false;

			return false;
		}

		$this->logs[] = $query;

		return $this->pdo->query($query);
	}

	public function exec($query)
	{
		if ($this->debug) {
			echo $query;

			$this->debug = false;

			return false;
		}

		$this->logs[] = $query;

		return $this->pdo->exec($query);
	}

	public function quote($string)
	{
		return $this->pdo->quote($string);
	}

	public function quoteColumn($string)
	{
		if (strpos($string, '#') === 0) {
			$string = substr($string, 1);
		}

		if ($string === '*') {
			return '*';
		}

		if (($p = strpos($string, '.')) !== false) { // table.column
			if ($string[$p + 1] === '*') {// table.*
				return '"' . $this->prefix . substr($string, 0, $p) . '".*';
			}

			return '"' . $this->prefix . str_replace('.', '"."', $string) . '"';
		}

		return '"' . $string . '"';
	}

	protected function columnPush($columns)
	{
		if ($columns == '*') {
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
				$value = $this->quoteColumn($match[1]);
				$stack[] = ($special ? $key . "(" . $value . ")" : $value) . ' AS ' . $this->quoteColumn($match[2]);
			} else {
				$value = $this->quoteColumn($value);
				$stack[] = $special ? $key . "(" . $value . ")" : $value;
			}
		}

		return implode(',', $stack);
	}


	protected function quoteArray($array)
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
			$haystack[] = '(' . $this->data_implode($value, $conjunctor) . ')';
		}
		return implode($outerConjunctor . ' ', $haystack);
	}

	protected function quoteFn($column, $string)
	{
		return (strpos($column, '#') === 0 && preg_match('/^[A-Z0-9\_]*\([^)]*\)$/', $string)) ? $string : $this->quote($string);
	}

	protected function quoteValue($column, $value)
	{
		switch (gettype($value)) {
			case 'NULL':
				return 'NULL';

			case 'array':
				return $this->quote(json_encode($value));

			case 'boolean':
				return $value ? '1' : '0';
				break;

			case 'integer':
			case 'double':
				return $value;

			case 'string':
				return $this->quoteFn($column, $value);
		}
	}

	protected function dataImplode($data, $conjunctor)
	{
		$wheres = [];

		foreach ($data as $key => $value) {
			$type = gettype($value);

			if (
				preg_match("/^(AND|OR)(\s+#.*)?$/i", $key, $relation) &&
				$type == 'array'
			) {
				$wheres[] = 0 !== count(array_diff_key($value, array_keys(array_keys($value)))) ?
					'(' . $this->dataImplode($value, ' ' . $relation[1]) . ')' :
					'(' . $this->innerConjunct($value, ' ' . $relation[1], $conjunctor) . ')';
			} else {
				preg_match('/(#?)([\w\.\-]+)(\[(\>|\>\=|\<|\<\=|\!|\<\>|\>\<|\!?~)\])?/i', $key, $match);
				$column = $this->quoteColumn($match[2]);

				if (isset($match[4])) {
					$operator = $match[4];

					if ($operator == '!') {
						switch ($type) {
							case 'NULL':
								$wheres[] = $column . ' IS NOT NULL';
								break;

							case 'array':
								$wheres[] = $column . ' NOT IN (' . $this->quoteArray($value) . ')';
								break;

							case 'integer':
							case 'double':
								$wheres[] = $column . ' != ' . $value;
								break;

							case 'boolean':
								$wheres[] = $column . ' != ' . ($value ? '1' : '0');
								break;

							case 'string':
								$wheres[] = $column . ' != ' . $this->quoteFn($key, $value);
								break;
						}
					}

					if ($operator == '<>' || $operator == '><') {
						if ($type == 'array') {
							if ($operator == '><') {
								$column .= ' NOT';
							}

							if (is_numeric($value[0]) && is_numeric($value[1])) {
								$wheres[] = '(' . $column . ' BETWEEN ' . $value[0] . ' AND ' . $value[1] . ')';
							} else {
								$wheres[] = '(' . $column . ' BETWEEN ' . $this->quote($value[0]) . ' AND ' . $this->quote($value[1]) . ')';
							}
						}
					}

					if ($operator == '~' || $operator == '!~') {
						if ($type == 'string') {
							$value = [$value];
						}

						if (!empty($value)) {
							$like = [];

							foreach ($value as $item) {
								$suffix = substr($item, -1, 1);
								if ($suffix === '_') {
									$item = substr_replace($item, '%', -1);
								} else if ($suffix === '%') {
									$item = '%' . substr_replace($item, '', -1, 1);
								} else if (preg_match('/^(?!%).+(?<!%)$/', $item)) {
									$item = '%' . $item . '%';
								}

								$like[] = $column . ($operator === '!~' ? ' NOT' : '') . ' LIKE ' . $this->quoteFn($key, $item);
							}

							$wheres[] = implode(' OR ', $like);
						}
					}

					if (in_array($operator, ['>', '>=', '<', '<='])) {
						if (is_numeric($value)) {
							$wheres[] = $column . ' ' . $operator . ' ' . $value;
						} elseif (strpos($key, '#') === 0) {
							$wheres[] = $column . ' ' . $operator . ' ' . $this->quoteFn($key, $value);
						} else {
							$wheres[] = $column . ' ' . $operator . ' ' . $this->quote($value);
						}
					}
				} else {
					switch ($type) {
						case 'NULL':
							$wheres[] = $column . ' IS NULL';
							break;

						case 'array':
							$wheres[] = $column . ' IN (' . $this->quoteArray($value) . ')';
							break;

						case 'integer':
						case 'double':
							$wheres[] = $column . ' = ' . $value;
							break;

						case 'boolean':
							$wheres[] = $column . ' = ' . ($value ? '1' : '0');
							break;

						case 'string':
							$wheres[] = $column . ' = ' . $this->quoteFn($key, $value);
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
		foreach (['GROUP', 'ORDER', 'LIMIT'] as $v) {
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

			$single_condition = array_diff($whereKeys,
				['AND', 'OR', 'GROUP', 'ORDER', 'HAVING', 'LIMIT', 'LIKE', 'MATCH']
			);

			if ($single_condition != []) {
				$clause = ' WHERE ' . $this->dataImplode($single_condition, '');
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
					$clause .= ($clause != '' ? ' AND ' : ' WHERE ') . ' MATCH (' . implode(', ', array_map([$this, 'quoteColumn'], $MATCH['columns'])) . ') AGAINST (' . $this->quote($MATCH['keyword']) . ')';
				}
			}

			if (isset($where['GROUP'])) {
				$clause .= ' GROUP BY ' . implode(', ', array_map([$this, 'quoteColumn'], (array) $where['GROUP']));

				if (isset($where['HAVING'])) {
					$clause .= ' HAVING ' . $this->dataImplode($where['HAVING'], ' AND');
				}
			}

			if (isset($where['ORDER'])) {
				$rsort = '/(^[a-zA-Z0-9_\-\.]*)(\s*(DESC|ASC))?/';
				$ORDER = $where['ORDER'];

				if (is_array($ORDER)) {
					if (
						isset($ORDER[1]) &&
						is_array($ORDER[1])
					) {
						$clause .= ' ORDER BY FIELD(' . $this->quoteColumn($ORDER[0]) . ', ' . $this->quoteArray($ORDER[1]) . ')';
					} else {
						$stack = [];
						foreach ($ORDER as $column) {
							preg_match($rsort, $column, $match);
							$stack[] = '"' . $this->quoteColumn($match[1]) . '" ' . ($match[3] ?? '');
						}
						$clause .= ' ORDER BY ' . implode(',', $stack);
					}
				} else {
					preg_match($rsort, $ORDER, $match);
					$clause .= ' ORDER BY "' . $this->quoteColumn($match[1]) . '" ' . ($match[3] ?? '');
				}
			}

			if (isset($where['LIMIT'])) {
				$LIMIT = $where['LIMIT'];

				if (is_numeric($LIMIT)) {
					$clause .= ' LIMIT ' . $LIMIT;
				}

				if (
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

	protected function selectContext($struct)
	{
		if (isset($struct['FROM'])) {
			$table = $struct['FROM'];
			$columns = $struct['SELECT'] ?? $struct['COLUMNS'] ?? '*';
		} else if (isset($struct['TABLE'])) {
			$table = $struct['TABLE'];
			$columns = $struct['SELECT'] ?? $struct['COLUMNS'] ?? '*';
		} else {
			$table = $struct['SELECT'];
			$columns = $struct['COLUMNS'] ?? '*';
		}

		$table = '"' . $this->prefix . $table . '"';
		if (isset($struct['JOIN'])) {
			$join = [];
			$joinSign = [
				'>' => 'LEFT',
				'<' => 'RIGHT',
				'<>' => 'FULL',
				'><' => 'INNER',
			];

			foreach ($struct['JOIN'] as $sub_table => $relation) {
				preg_match('/(\[(\<|\>|\>\<|\<\>)\])?([a-zA-Z0-9_\-]*)\s?(\(([a-zA-Z0-9_\-]*)\))?/', $sub_table, $match);

				if ($match[2] != '' && $match[3] != '') {
					if (is_string($relation)) {
						$relation = 'USING ("' . $relation . '")';
					}

					if (is_array($relation)) {
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
									'"' . (isset($match[5]) ? $match[5] : $match[3]) . '"."' . $value . '"';
							}

							$relation = 'ON ' . implode(' AND ', $joins);
						}
					}

					$join[] = $joinSign[$match[2]] . ' JOIN "' . $this->prefix . $match[3] . '" ' . (isset($match[5]) ? 'AS "' . $match[5] . '" ' : '') . $relation;
				}
			}

			$table .= ' ' . implode(' ', $join);
		}

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

		return 'SELECT ' . $column . ' FROM ' . $table . $this->suffixClause($struct);
	}

	public function headers($table)
	{
		$sth = $this->pdo->query('SELECT * FROM "' . $this->prefix . $table . '"');

		$headers = [];
		for ($i = 0, $n = $sth->columnCount(); $i < $n; ++$i) {
			$headers[] = $sth->getColumnMeta($i);
		}

		return $headers;
	}

	public function select(array $struct, $fetch = PDO::FETCH_ASSOC, $fetchArgs = null)
	{
		$query = $this->query(
			$this->selectContext($struct)
		);

		return $query ? $query->fetchAll($fetch, $fetchArgs) : false;
	}

	public function insert($table, $datas = [])
	{
		if (is_array($table)) {
			$datas = $table['VALUES'] ?? $table['SET'];
			$table = $table['FROM'] ?? $table['TABLE'] ?? $table['INSERT'];
		}

		$lastId = [];

		// Check indexed or associative array
		if (!isset($datas[0])) {
			$datas = [$datas];
		}

		foreach ($datas as $data) {
			$values = [];
			$columns = [];

			foreach ($data as $key => $value) {
				$columns[] = $this->quoteColumn($key);
				$values[] = $this->quoteValue($key, $value);
			}

			$this->exec('INSERT INTO "' . $this->prefix . $table . '" (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')');

			$lastId[] = $this->pdo->lastInsertId();
		}

		return count($lastId) > 1 ? $lastId : $lastId[0];
	}

	public function update($table, $data = [], $where = null)
	{
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
					$fields[] = $this->quoteColumn($match[1]) . ' = ' . $this->quoteColumn($match[1]) . ' ' . $match[3] . ' ' . $value;
				}
			} else {
				$column = $this->quoteColumn($key);
				$fields[] = $column . ' = ' . $this->quoteValue($key, $value);
			}
		}

		return $this->exec('UPDATE "' . $this->prefix . $table . '" SET ' . implode(', ', $fields) . $where);
	}

	public function delete($table, $where = null)
	{
		if (is_array($table)) {
			$where = $this->suffixClause($table);
			$table = $table['FROM'] ?? $table['TABLE'] ?? $table['DELETE'];
		} else {
			$where = $this->whereClause($where);
		}

		return $this->exec('DELETE FROM "' . $this->prefix . $table . '"' . $where);
	}

	public function replace($table, $columns, $search = null, $replace = null, $where = null)
	{
		if (is_array($columns)) {
			$replace_query = [];

			foreach ($columns as $column => $replacements) {
				foreach ($replacements as $k => $v) {
					$replace_query[] = $column . ' = REPLACE(' . $this->quoteColumn($column) . ', ' . $this->quote($k) . ', ' . $this->quote($v) . ')';
				}
			}

			$replace_query = implode(', ', $replace_query);
			$where = $search;
		} else if (is_array($search)) {
			$replace_query = [];
			foreach ($search as $k => $v) {
				$replace_query[] = $columns . ' = REPLACE(' . $this->quoteColumn($columns) . ', ' . $this->quote($k) . ', ' . $this->quote($v) . ')';
			}
			$replace_query = implode(', ', $replace_query);
			$where = $replace;
		} else {
			$replace_query = $columns . ' = REPLACE(' . $this->quoteColumn($columns) . ', ' . $this->quote($search) . ', ' . $this->quote($replace) . ')';
		}

		return $this->exec('UPDATE "' . $this->prefix . $table . '" SET ' . $replace_query . $this->whereClause($where));
	}

	public function get(array $struct, $fetch = PDO::FETCH_ASSOC, $fetchArgs = null)
	{
		$query = $this->query(
			$this->selectContext($struct) . ' LIMIT 1'
		);

		if ($query) {
			if (empty($fetchArgs)) {
				$data = $query->fetch($fetch);
			} else {
				$fetchArgs = (array) $fetchArgs;
				array_unshift($fetchArgs, $fetch);
				call_user_func_array([$query, 'setFetchMode'], $fetchArgs);
				$data = $query->fetch($fetch);
			}

			if (is_object($data)) {
				return $data;
			} else if (!empty($data)) {
				$column = $struct['COLUMNS'] ?? null;
				if (is_string($column) && $column !== '*' && strpos($column, ',') !== false) {
					return $data[$column];
				}

				return $data;
			}

			return [];
		}

		return false;
	}

	public function has(array $struct)
	{
		if (isset($struct['COLUMNS'])) {
			unset($struct['COLUMNS']);
		}
		$struct['FUN'] = 1;

		$query = $this->query(
			'SELECT EXISTS(' . $this->selectContext($struct) . ')'
		);

		return $query ? $query->fetchColumn() === '1' : false;
	}

	public function count(array $struct)
	{
		$struct['FUN'] = 'COUNT';
		$query = $this->query(
			$this->selectContext($struct)
		);

		return $query ? (int) $query->fetchColumn() : false;
	}

	public function max(array $struct)
	{
		$struct['FUN'] = 'MAX';
		$query = $this->query(
			$this->selectContext($struct)
		);

		if ($query) {
			$max = $query->fetchColumn();

			return is_numeric($max) ? (int) $max : $max;
		} else {
			return false;
		}
	}

	public function min(array $struct)
	{
		$struct['FUN'] = 'MIN';
		$query = $this->query(
			$this->selectContext($struct)
		);

		if ($query) {
			$min = $query->fetchColumn();
			return is_numeric($min) ? (int) $min : $min;
		} else {
			return false;
		}
	}

	public function avg(array $struct)
	{
		$struct['FUN'] = 'AVG';
		$query = $this->query(
			$this->selectContext($struct)
		);

		return $query ? (int) $query->fetchColumn() : false;
	}

	public function sum(array $struct)
	{
		$struct['FUN'] = 'SUM';
		$query = $this->query(
			$this->selectContext($struct)
		);

		return $query ? (int) $query->fetchColumn() : false;
	}

	public function action($actions)
	{
		if (is_callable($actions)) {
			$this->pdo->beginTransaction();

			$result = $actions($this);

			if ($result === false) {
				$this->pdo->rollBack();
			} else {
				$this->pdo->commit();
			}
		} else {
			return false;
		}
	}

	public function debug()
	{
		$this->debug = true;

		return $this;
	}

	public function error()
	{
		return $this->pdo->errorInfo();
	}

	public function lastQuery()
	{
		return end($this->logs);
	}

	public function log()
	{
		return $this->logs;
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
			$output[$key] = $this->pdo->getAttribute(constant('PDO::ATTR_' . $value));
		}

		return $output;
	}

	/**
	 * @param   string $sql the sql statement
	 * @param   string|array $binds an array/string of binding data
	 * @return  string
	 */
	public function bindCompile($sql, $binds = null)
	{
		if (empty($binds) || strpos($sql, '?') === false) {
			return $sql;
		} else if (!is_array($binds)) {
			$binds = [$binds];
			$bind_count = 1;
		} else {
			// Make sure we're using numeric keys
			$binds = array_values($binds);
			$bind_count = count($binds);
		}

		// Make sure not to replace a chunk inside a string that happens to match the bind marker
		if ($c = preg_match_all("/'[^']*'/i", $sql, $matches)) {
			$c = preg_match_all('/\?/i',
				str_replace($matches[0],
					str_replace('?', ' ', $matches[0]),
					$sql, $c
				),
				$matches, PREG_OFFSET_CAPTURE
			);

			// Bind values' count must match the count of markers in the query
			if ($bind_count !== $c) {
				return $sql;
			}
		} else if (($c = preg_match_all('/\?/i', $sql, $matches, PREG_OFFSET_CAPTURE)) !== $bind_count) {
			return $sql;
		}

		do {
			$c--;
			$escaped_value = $this->quoteValue('', $binds[$c]);
			$sql = substr_replace($sql, $escaped_value, $matches[0][$c][1], 1);
		} while ($c !== 0);

		return $sql;
	}
}
