<?php
/*!
 * Medoo database framework
 * http://medoo.in
 * Version 1.5.2
 *
 * Copyright 2017, Angel Lai
 * Released under the MIT license
 */

namespace Hail\Database\Sql;

use Hail\Database\Database;
use Hail\Util\Json;
use PDO;

/**
 * SQL builder from Medoo
 *
 * @package Hail\Database
 * @author  Feng Hao <flyinghail@msn.com>
 */
class Builder
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var Database $db
     */
    protected $db;

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * @var string
     */
    protected $quote = '"';

    /**
     * @var int
     */
    protected $guid = 0;

    public function __construct(Database $db, string $prefix = null)
    {
        $this->type = $db->getType();
        $this->db = $db;

        if ($this->type === 'mysql' || $this->type === 'mariadb') {
            $this->quote = '`';
        }

        if ($prefix) {
            $this->prefix = $prefix;
        }
    }

    public function raw(string $string, array $map = []): Raw
    {
        return new Raw($string, $map);
    }

    public function buildRaw($raw, &$map)
    {
        if (!$raw instanceof Raw) {
            return null;
        }

        $query = \preg_replace_callback(
            '/((FROM|TABLE|INTO|UPDATE)\s*)?\<([a-zA-Z0-9_\.]+)\>/i',
            [$this, 'buildRawCallback'],
            $raw->value
        );

        $rawMap = $raw->map;

        if (!empty($rawMap)) {
            foreach ($rawMap as $key => $value) {
                $rawMap[$key] = $this->typeMap($value, \gettype($value));
            }

            $map = $rawMap;
        }

        return $query;
    }

    protected function buildRawCallback(array $matches): string
    {
        if (!empty($matches[2])) {
            return $matches[2] . ' ' . $this->tableQuote($matches[3]);
        }

        return $this->columnQuote($matches[3]);
    }

    protected function typeMap($value, string $type): array
    {
        static $map = [
            'NULL' => PDO::PARAM_NULL,
            'integer' => PDO::PARAM_INT,
            'double' => PDO::PARAM_STR,
            'boolean' => PDO::PARAM_BOOL,
            'string' => PDO::PARAM_STR,
            'object' => PDO::PARAM_STR,
            'resource' => PDO::PARAM_LOB,
            'array' => PDO::PARAM_STR,
        ];

        if ($type === 'boolean') {
            $value = ($value ? '1' : '0');
        } elseif ($type === 'NULL') {
            $value = null;
        } elseif ($type === 'array') {
            $value = Json::encode($value);
        }

        return [$value, $map[$type]];
    }

    public function generate(string $query, array $map)
    {
        $identifier = [
            'mysql' => '`$1`',
            'mariadb' => '`$1`',
            'mssql' => '[$1]',
        ];

        $query = \preg_replace(
            '/"(\w+)"/i',
            $identifier[$this->type] ?? '"$1"',
            $query
        );

        foreach ($map as $key => $value) {
            if ($value[1] === PDO::PARAM_STR) {
                $replace = $this->db->quote($value[0]);
            } elseif ($value[1] === PDO::PARAM_NULL) {
                $replace = 'NULL';
            } elseif ($value[1] === PDO::PARAM_LOB) {
                $replace = '{LOB_DATA}';
            } else {
                $replace = $value[0];
            }

            $query = \str_replace($key, $replace, $query);
        }

        return $query;
    }

    /**
     * @param string $prefix
     *
     * @return $this
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param string $quote
     *
     * @return $this
     */
    public function setQuote(string $quote): self
    {
        $this->quote = $quote;

        return $this;
    }

    protected function tableQuote($table)
    {
        if (\strpos($table, '.') !== false) { // database.table
            return $this->quote . \str_replace('.', $this->quote . '.' . $this->quote . $this->prefix,
                    $table) . $this->quote;
        }

        return $this->quote . $this->prefix . $table . $this->quote;
    }

    protected function mapKey(): string
    {
        $index = (string)$this->guid;
        ++$this->guid;

        return ":HaIl_{$index}_hAiL";
    }

    protected function columnQuote(string $string): string
    {
        if ($string === '*') {
            return '*';
        }

        $quote = $this->quote;

        if (($p = \strpos($string, '.')) !== false) { // table.column
            if ($string[$p + 1] === '*') {// table.*
                return $quote . $this->prefix . \substr($string, 0, $p) . $quote . '.*';
            }

            return $quote . $this->prefix . \str_replace('.', $quote . '.' . $quote,
                    $string) . $quote;
        }

        return $quote . $string . $quote;
    }

    protected function columnPush(&$columns, &$map)
    {
        if ($columns === '*') {
            return $columns;
        }

        if (\is_string($columns)) {
            $columns = [$columns];
        }

        $stack = [];
        foreach ($columns as $key => $value) {
            if (\is_array($value)) {
                $stack[] = $this->columnPush($value, $map);
            } elseif (!\is_int($key) && $raw = $this->buildRaw($value, $map)) {
                \preg_match('/(?<column>[a-zA-Z0-9_\.]+)(\s*\[(?<type>(String|Bool|Int|Number))\])?/i', $key, $match);

                $stack[] = $raw . ' AS ' . $this->columnQuote($match['column']);
            } elseif (\is_int($key) && \is_string($value)) {
                \preg_match('/(?<column>[a-zA-Z0-9_\.]+)(?:\s*\((?<alias>\w+)\))?(?:\s*\[(?<type>(?:String|Bool|Int|Number|Object|JSON))\])?/i',
                    $value, $match);

                if (!empty($match['alias'])) {
                    $stack[] = $this->columnQuote($match['column']) . ' AS ' . $this->columnQuote($match['alias']);

                    $columns[$key] = $match['alias'];

                    if (!empty($match['type'])) {
                        $columns[$key] .= ' [' . $match['type'] . ']';
                    }
                } else {
                    $stack[] = $this->columnQuote($match['column']);
                }
            }
        }

        return \implode(',', $stack);
    }


    protected function arrayQuote(array $array): string
    {
        $temp = [];
        foreach ($array as $value) {
            $temp[] = \is_int($value) ? $value : $this->db->quote($value);
        }

        return \implode(',', $temp);
    }

    protected function innerConjunct(array $data, array $map, string $conjunctor, string $outerConjunctor): string
    {
        $stack = [];
        foreach ($data as $value) {
            $stack[] = '(' . $this->dataImplode($value, $map, $conjunctor) . ')';
        }

        return \implode($outerConjunctor . ' ', $stack);
    }

    protected function dataImplode(array $data, array &$map, string $conjunctor)
    {
        $stack = [];

        foreach ($data as $key => $value) {
            $type = \gettype($value);

            if (
                $type === 'array' &&
                \preg_match("/^(AND|OR)(\s+#.*)?$/", $key, $relation_match)
            ) {
                $relationship = $relation_match[ 1 ];

                $stack[] = $value !== \array_keys(\array_keys($value)) ?
                    '(' . $this->dataImplode($value, $map, ' ' . $relationship) . ')' :
                    '(' . $this->innerConjunct($value, $map, ' ' . $relationship, $conjunctor) . ')';

                continue;
            }

            $mapKey = $this->mapKey();

            if (
                \is_int($key) &&
                \preg_match('/([a-zA-Z0-9_\.]+)\[(?<operator>\>\=?|\<\=?|\!|\=)\]([a-zA-Z0-9_\.]+)/i', $value, $match)
            ) {
                $stack[] = $this->columnQuote($match[1]) . ' ' . $match['operator'] . ' ' . $this->columnQuote($match[3]);
            } else {
                \preg_match('/([a-zA-Z0-9_\.]+)(\[(?<operator>\>\=?|\<\=?|\!|\<\>|\>\<|\!?~|REGEXP)\])?/i', $key,
                    $match);
                $column = $this->columnQuote($match[1]);

                if (isset($match['operator'])) {
                    $operator = $match['operator'];

                    if (\in_array($operator, ['>', '>=', '<', '<='], true)) {
                        $condition = $column . ' ' . $operator . ' ';

                        if (\is_numeric($value)) {
                            $condition .= $mapKey;
                            $map[$mapKey] = [$value, PDO::PARAM_INT];
                        } elseif ($raw = $this->buildRaw($value, $map)) {
                            $condition .= $raw;
                        } else {
                            $condition .= $mapKey;
                            $map[$mapKey] = [$value, PDO::PARAM_STR];
                        }

                        $stack[] = $condition;
                    } elseif ($operator === '!') {
                        switch ($type) {
                            case 'NULL':
                                $stack[] = $column . ' IS NOT NULL';
                                break;

                            case 'array':
                                $placeholders = [];

                                foreach ($value as $index => $item) {
                                    $placeholders[] = $mapKey . $index;
                                    $map[$mapKey . $index] = $this->typeMap($item, \gettype($item));
                                }

                                $stack[] = $column . ' NOT IN (' . \implode(', ', $placeholders) . ')';
                                break;

                            case 'object':
                                if ($raw = $this->buildRaw($value, $map)) {
                                    $stack[] = $column . ' != ' . $raw;
                                }
                                break;

                            case 'integer':
                            case 'double':
                            case 'boolean':
                            case 'string':
                                $stack[] = $column . ' != ' . $mapKey;
                                $map[$mapKey] = $this->typeMap($value, $type);
                                break;
                        }
                    } elseif ($operator === '~' || $operator === '!~') {
                        if ($type !== 'array') {
                            $value = [$value];
                        }

                        $connector = ' OR ';
                        $data = \array_values($value);

                        if (\is_array($data[0])) {
                            if (isset($value[SQL::AND]) || isset($value[SQL::OR])) {
                                $connector = ' ' . \array_keys($value)[0] . ' ';
                                $value = $data[0];
                            }
                        }

                        $like = [];

                        foreach ($value as $index => $item) {
                            $item = (string)$item;

                            if (!\preg_match('/(\[.+\]|_|%.+|.+%)/', $item)) {
                                $item = '%' . $item . '%';
                            }

                            $like[] = $column . ($operator === '!~' ? ' NOT' : '') . ' LIKE ' . $mapKey . 'L' . $index;
                            $map[$mapKey . 'L' . $index] = [$item, PDO::PARAM_STR];
                        }

                        $stack[] = '(' . \implode($connector, $like) . ')';
                    } elseif ($operator === '<>' || $operator === '><') {
                        if ($type === 'array') {
                            if ($operator === '><') {
                                $column .= ' NOT';
                            }

                            $stack[] = '(' . $column . ' BETWEEN ' . $mapKey . 'a AND ' . $mapKey . 'b)';

                            $dataType = (\is_numeric($value[0]) && \is_numeric($value[1])) ? PDO::PARAM_INT : PDO::PARAM_STR;

                            $map[$mapKey . 'a'] = [$value[0], $dataType];
                            $map[$mapKey . 'b'] = [$value[1], $dataType];
                        }
                    } elseif ($operator === 'REGEXP') {
                        $stack[] = $column . ' REGEXP ' . $mapKey;
                        $map[$mapKey] = [$value, PDO::PARAM_STR];
                    }
                } else {
                    switch ($type) {
                        case 'NULL':
                            $stack[] = $column . ' IS NULL';
                            break;

                        case 'array':
                            $placeholders = [];

                            foreach ($value as $index => $item) {
                                $placeholders[] = $mapKey . $index;
                                $map[$mapKey . $index] = $this->typeMap($item, \gettype($item));
                            }

                            $stack[] = $column . ' IN (' . \implode(', ', $placeholders) . ')';
                            break;

                        case 'object':
                            if ($raw = $this->buildRaw($value, $map)) {
                                $stack[] = $column . ' = ' . $raw;
                            }
                            break;

                        case 'integer':
                        case 'double':
                        case 'boolean':
                        case 'string':
                            $stack[] = $column . ' = ' . $mapKey;
                            $map[$mapKey] = $this->typeMap($value, $type);
                            break;
                    }
                }
            }
        }

        return \implode($conjunctor . ' ', $stack);
    }

    protected function suffixClause(array $struct, &$map): string
    {
        $where = $struct[SQL::WHERE] ?? [];
        foreach ([SQL::GROUP, SQL::ORDER, SQL::LIMIT, SQL::HAVING, SQL::LIKE, SQL::MATCH] as $v) {
            if (isset($struct[$v]) && !isset($where[$v])) {
                $where[$v] = $struct[$v];
            }
        }

        return $this->whereClause($where, $map);
    }

    protected function whereClause(array $where, array &$map): string
    {
        if (empty($where)) {
            return '';
        }

        $clause = '';
        if (\is_array($where)) {
            $conditions = \array_diff_key($where, \array_flip(
                [SQL::GROUP, SQL::ORDER, SQL::HAVING, SQL::LIMIT, SQL::LIKE, SQL::MATCH]
            ));

            if (!empty($conditions)) {
                $clause = ' WHERE ' . $this->dataImplode($conditions, $map, ' AND');
            }

            if (isset($where[SQL::MATCH])) {
                $MATCH = $where[SQL::MATCH];

                if (\is_array($MATCH) && isset($MATCH['columns'], $MATCH['keyword'])) {
                    $mode = '';

                    static $mode_array = [
                        'natural' => 'IN NATURAL LANGUAGE MODE',
                        'natural+query' => 'IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION',
                        'boolean' => 'IN BOOLEAN MODE',
                        'query' => 'WITH QUERY EXPANSION',
                    ];

                    if (isset($MATCH['mode'], $mode_array[$MATCH['mode']])) {
                        $mode = ' ' . $mode_array[$MATCH['mode']];
                    }

                    $columns = \implode(', ', \array_map([$this, 'columnQuote'], $MATCH['columns']));
                    $mapKey = $this->mapKey();
                    $map[$mapKey] = [$MATCH['keyword'], PDO::PARAM_STR];

                    $clause .= ($clause !== '' ? ' AND ' : ' WHERE') . ' MATCH (' . $columns . ') AGAINST (' . $mapKey . $mode . ')';
                }
            }

            if (isset($where[SQL::GROUP])) {
                $GROUP = $where[SQL::GROUP];

                if (\is_array($GROUP)) {
                    $clause .= ' GROUP BY ' . \implode(',', \array_map([$this, 'columnQuote'], $GROUP));
                } elseif ($raw = $this->buildRaw($GROUP, $map)) {
                    $clause .= ' GROUP BY ' . $raw;
                } else {
                    $clause .= ' GROUP BY ' . $this->columnQuote($GROUP);
                }

                if (isset($where[SQL::HAVING])) {
                    $HAVING = $where[SQL::HAVING];
                    if ($raw = $this->buildRaw($HAVING, $map)) {
                        $clause .= ' HAVING ' . $raw;
                    } else {
                        $clause .= ' HAVING ' . $this->dataImplode($HAVING, $map, ' AND');
                    }
                }
            }

            $LIMIT = null;
            if (isset($where[SQL::LIMIT])) {
                $LIMIT = $where[SQL::LIMIT];
            }

            if (isset($where[SQL::ORDER])) {
                $ORDER = $where[SQL::ORDER];

                if (\is_array($ORDER)) {
                    $stack = [];

                    foreach ($ORDER as $column => $value) {
                        if (\is_array($value)) {
                            $stack[] = 'FIELD(' . $this->columnQuote($column) . ', ' . $this->arrayQuote($value) . ')';
                        } elseif ($value === 'ASC' || $value === 'DESC') {
                            $stack[] = $this->columnQuote($column) . ' ' . $value;
                        } elseif (\is_int($column)) {
                            $stack[] = $this->columnQuote($value);
                        }
                    }

                    $clause .= ' ORDER BY ' . implode($stack, ',');
                } elseif ($raw = $this->buildRaw($ORDER, $map)) {
                    $clause .= ' ORDER BY ' . $raw;
                } else {
                    $clause .= ' ORDER BY ' . $this->columnQuote($ORDER);
                }

                if (null !== $LIMIT && \in_array($this->type, ['oracle', 'mssql'], true)) {
                    if (\is_numeric($LIMIT)) {
                        $LIMIT = [0, $LIMIT];
                    }

                    if (
                        \is_array($LIMIT) &&
                        \is_numeric($LIMIT[0]) &&
                        \is_numeric($LIMIT[1])
                    ) {
                        $clause .= ' OFFSET ' . $LIMIT[0] . ' ROWS FETCH NEXT ' . $LIMIT[1] . ' ROWS ONLY';
                    }
                }
            }

            if (null !== $LIMIT && !\in_array($this->type, ['oracle', 'mssql'], true)) {
                if (\is_numeric($LIMIT)) {
                    $clause .= ' LIMIT ' . $LIMIT;
                } elseif (
                    \is_array($LIMIT) &&
                    \is_numeric($LIMIT[0]) &&
                    \is_numeric($LIMIT[1])
                ) {
                    $clause .= ' LIMIT ' . $LIMIT[1] . ' OFFSET ' . $LIMIT[0];
                }
            }
        } elseif ($raw = $this->buildRaw($where, $map)) {
            $clause .= ' ' . $raw;
        }

        return $clause;
    }

    protected function getTable(array $struct, string $key = null): string
    {
        $table = $struct[SQL::TABLE] ?? $struct[SQL::FROM] ?? ($key ? $struct[$key] : null);

        if (empty($table)) {
            throw new \InvalidArgumentException('SQL array must contains table.');
        }

        return $table;
    }

    public function getColumns($struct, $wildcard = true)
    {
        if (isset($struct[SQL::COLUMNS])) {
            return $struct[SQL::COLUMNS];
        }

        if (
            isset($struct[SQL::TABLE], $struct[SQL::SELECT]) ||
            isset($struct[SQL::FROM], $struct[SQL::SELECT])
        ) {
            return $struct[SQL::SELECT];
        }

        if (!$wildcard) {
            throw new \InvalidArgumentException('SQL array must contains columns');
        }

        return '*';
    }

    /**
     * @param string|array $struct
     *
     * @return array
     */
    public function select($struct): array
    {
        if (\is_string($struct)) {
            $struct = [
                SQL::TABLE => $struct,
            ];
        }

        $map = [];
        $table = $this->getTable($struct);
        \preg_match('/(?<table>\w+)\s*\((?<alias>\w+)\)/i', $table, $tableMatch);

        if (isset($tableMatch['table'], $tableMatch['alias'])) {
            $table = $this->tableQuote($tableMatch['table']);
            $tableQuery = $table . ' AS ' . $this->tableQuote($tableMatch['alias']);
        } else {
            $table = $this->tableQuote($table);
            $tableQuery = $table;
        }

        $join = $struct[SQL::JOIN] ?? null;
        $joinKey = \is_array($join) ? \array_keys($join) : null;

        if (!empty($joinKey[0]) && $joinKey[0][0] === '[') {
            $tableJoin = [];

            static $joinArray = [
                '>' => 'LEFT',
                '<' => 'RIGHT',
                '<>' => 'FULL',
                '><' => 'INNER',
            ];

            $quote = $this->quote;

            foreach ($join as $sub => $relation) {
                \preg_match('/(\[(?<join>\<\>?|\>\<?)\])?(?<table>\w+)\s?(\((?<alias>\w+)\))?/',
                    $sub, $match);

                if ($match['join'] !== '' && $match['table'] !== '') {
                    if (\is_string($relation)) {
                        $relation = 'USING (' . $quote . $relation . $quote . ')';
                    }

                    if (\is_array($relation)) {
                        // For ['column1', 'column2']
                        if (isset($relation[0])) {
                            $relation = 'USING (' . $quote . \implode($quote . ', ' . $quote, $relation) . $quote . ')';
                        } else {
                            $joins = [];

                            foreach ($relation as $key => $value) {
                                $joins[] = (
                                    \strpos($key, '.') > 0 ?
                                        // For ['tableB.column' => 'column']
                                        $this->columnQuote($key) :

                                        // For ['column1' => 'column2']
                                        $table . '.' . $quote . $key . $quote
                                    ) .
                                    ' = ' .
                                    $this->tableQuote($match['alias'] ?? $match['table']) . '.' . $quote . $value . $quote;
                            }

                            $relation = 'ON ' . \implode(' AND ', $joins);
                        }
                    }

                    $tableName = $this->tableQuote($match['table']) . ' ';

                    if (isset($match['alias'])) {
                        $tableName .= 'AS ' . $this->tableQuote($match['alias']) . ' ';
                    }

                    $tableJoin[] = $joinArray[$match['join']] . ' JOIN ' . $tableName . $relation;
                }
            }

            $tableQuery .= ' ' . \implode(' ', $join);
        }

        $columns = $this->getColumns($struct);
        if (isset($struct[SQL::FUN])) {
            $fn = $struct[SQL::FUN];
            if ($fn === 1 || $fn === '1') {
                $column = '1';
            } else {
                $column = $fn . '(' . $this->columnPush($columns, $map) . ')';
            }
        } else {
            $column = $this->columnPush($columns, $map);
        }

        $sql = 'SELECT ' . $column . ' FROM ' . $tableQuery . $this->suffixClause($struct, $map);

        return [$sql, $map];
    }

    /**
     * @param string|array $table
     * @param string|array $datas
     * @param string|array $INSERT
     *
     * @return array
     */
    public function insert($table, $datas = [], $INSERT = SQL::INSERT): array
    {
        if (\is_array($table)) {
            $datas = $table[SQL::VALUES] ?? $table[SQL::SET];
            $table = $this->getTable($table, SQL::INSERT);

            if (\is_string($datas)) {
                $INSERT = $datas;
            }
        }

        if (\is_string($INSERT) && \strpos($INSERT, ' ') !== false) {
            $INSERT = \array_map('\trim', \explode(' ', $INSERT));
        }

        if (\is_array($INSERT)) {
            $do = \in_array(SQL::REPLACE, $INSERT, true) ? 'REPLACE' : 'INSERT';
            $parts = [$do];

            $subs = [
                SQL::LOW_PRIORITY => 'LOW_PRIORITY',
                SQL::DELAYED => 'DELAYED',
            ];
            if ($do === SQL::INSERT) {
                $subs[SQL::HIGH_PRIORITY] = 'HIGH_PRIORITY';
            }
            foreach ($subs as $k => $sub) {
                if (\in_array($k, $INSERT, true)) {
                    $parts[] = $sub;
                    break;
                }
            }

            if ($do === SQL::INSERT && \in_array(SQL::IGNORE, $INSERT, true)) {
                $parts[] = 'IGNORE';
            }

            $INSERT = \implode(' ', $parts);
        } else {
            $INSERT = $INSERT === SQL::REPLACE ? 'REPLACE' : 'INSERT';
        }

        // Check indexed or associative array
        if (!isset($datas[0])) {
            $datas = [$datas];
        }

        $columns = \array_keys($datas[0]);

        $stack = [];
        $map = [];

        foreach ($datas as $data) {
            if (\array_keys($data) !== $columns) {
                throw new \InvalidArgumentException('Some rows contain inconsistencies');
            }

            $values = [];
            foreach ($columns as $key) {
                if ($raw = $this->buildRaw($data[$key], $map)) {
                    $values[] = $raw;
                    continue;
                }

                $values[] = $mapKey = $this->mapKey();

                if (!isset($data[$key])) {
                    $map[$mapKey] = [null, PDO::PARAM_NULL];
                } else {
                    $value = $data[$key];
                    $map[$mapKey] = $this->typeMap($value, \gettype($value));
                }

            }

            $stack[] = '(' . \implode(', ', $values) . ')';
        }

        $columns = \array_map([$this, 'columnQuote'], $columns);

        $sql = $INSERT . ' INTO ' . $this->tableQuote($table) .
            ' (' . \implode(', ', $columns) . ') VALUES ' . \implode(', ', $stack);

        return [$sql, $map];
    }

    /**
     * @param       $table
     * @param array $data
     * @param mixed $where
     *
     * @return array
     */
    public function update($table, array $data = [], $where = null): array
    {
        if (\is_array($table)) {
            $data = $table[SQL::SET] ?? $table[SQL::VALUES];
            $where = $table;
            $table = $this->getTable($table, SQL::UPDATE);
        } elseif ($where) {
            $where = [SQL::WHERE => $where];
        }

        $fields = [];
        $map = [];

        foreach ($data as $key => $value) {
            $column = $this->columnQuote(\preg_replace("/(\s*\[(\+|\-|\*|\/)\]$)/i", '', $key));

            if ($raw = $this->buildRaw($value, $map)) {
                $fields[] = $column . ' = ' . $raw;
                continue;
            }

            $mapKey = $this->mapKey();
            \preg_match('/(?<column>\w+)(\[(?<operator>\+|\-|\*|\/)\])?/i', $key, $match);

            if (isset($match['operator'])) {
                if (\is_numeric($value)) {
                    $fields[] = $column . ' = ' . $column . ' ' . $match['operator'] . ' ' . $value;
                }
            } else {
                $fields[] = $column . ' = ' . $mapKey;
                $map[$mapKey] = $this->typeMap($value, \gettype($value));
            }
        }

        $sql = 'UPDATE ' . $this->tableQuote($table) . ' SET ' . \implode(', ', $fields) .
            $this->suffixClause($where, $map);

        return [$sql, $map];
    }

    /**
     * @param string|array $table
     * @param array|null   $where
     *
     * @return array
     */
    public function delete($table, $where = null): array
    {
        $map = [];
        if (\is_array($table)) {
            $where = $table;
            $table = $this->getTable($table, SQL::DELETE);
        } elseif ($where) {
            $where = [SQL::WHERE => $where];
        }

        $sql = 'DELETE FROM ' . $this->tableQuote($table) . $this->suffixClause($where, $map);

        return [$sql, $map];
    }

    /**
     * @param array|string $table
     * @param array|null   $columns
     * @param array|null   $where
     *
     * @return array|null
     */
    public function replace($table, array $columns = null, array $where = null): ?array
    {
        if (\is_array($table)) {
            $columns = $this->getColumns($table, false);
            $table = $this->getTable($table);
            $where = $table;
        } elseif ($where) {
            $where = [SQL::WHERE => $where];
        }

        if (!\is_array($columns) || empty($columns)) {
            return null;
        }

        $map = [];
        $stack = [];

        foreach ($columns as $column => $replacements) {
            if (\is_array($replacements)) {
                foreach ($replacements as $old => $new) {
                    $mapKey = $this->mapKey();

                    $stack[] = $this->columnQuote($column) . ' = REPLACE(' . $this->columnQuote($column) . ', ' . $mapKey . 'a, ' . $mapKey . 'b)';

                    $map[$mapKey . 'a'] = [$old, PDO::PARAM_STR];
                    $map[$mapKey . 'b'] = [$new, PDO::PARAM_STR];
                }
            }
        }

        if ($stack === []) {
            return null;
        }

        $sql = 'UPDATE ' . $this->tableQuote($table) . ' SET ' .
            \implode(', ', $stack) . $this->suffixClause($where, $map);

        return [$sql, $map];
    }

    /**
     * @param array $struct
     *
     * @return array
     */
    public function has(array $struct): array
    {
        unset($struct[SQL::COLUMNS], $struct[SQL::SELECT]);

        $struct[SQL::FUN] = 1;

        [$sql, $map] = $this->select($struct);

        return ['SELECT EXISTS(' . $sql . ')', $map];
    }

    /**
     * @param string $table
     *
     * @return string
     */
    public function truncate(string $table): string
    {
        return 'TRUNCATE TABLE ' . $this->tableQuote($table);
    }

    public function __destruct()
    {
        $this->db = null;
    }
}