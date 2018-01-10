<?php

namespace Hail\Database;

use Hail\Facade\DB;
use Hail\Database\Exception\ModelException;
use Hail\Util\Json;
use PDO;

/**
 * Simple implement of active record in PHP.
 * Using magic function to implement more smarty functions.
 * Can using chain method calls, to build concise and compactness program.
 *
 * @author Hao Feng <flyinghail#msn.com>
 *
 * @method $this equal($field, $value = null, $relation = 'AND')
 * @method $this eq($field, $value = null, $relation = 'AND')
 * @method $this notEqual($field, $value = null, $relation = 'AND')
 * @method $this ne($field, $value = null, $relation = 'AND')
 * @method $this greaterThan($field, $value = null, $relation = 'AND')
 * @method $this gt($field, $value = null, $relation = 'AND')
 * @method $this lessThan($field, $value = null, $relation = 'AND')
 * @method $this lt($field, $value = null, $relation = 'AND')
 * @method $this greaterThanOrEqual($field, $value = null, $relation = 'AND')
 * @method $this ge($field, $value = null, $relation = 'AND')
 * @method $this gte($field, $value = null, $relation = 'AND')
 * @method $this lessThanOrEqual($field, $value = null, $relation = 'AND')
 * @method $this le($field, $value = null, $relation = 'AND')
 * @method $this lte($field, $value = null, $relation = 'AND')
 * @method $this between($field, $value = null, $relation = 'AND')
 * @method $this bt($field, $value = null, $relation = 'AND')
 * @method $this notBetween($field, $value = null, $relation = 'AND')
 * @method $this nbt($field, $value = null, $relation = 'AND')
 * @method $this nb($field, $value = null, $relation = 'AND')
 * @method $this like($field, $value = null, $relation = 'AND')
 * @method $this notLike($field, $value = null, $relation = 'AND')
 * @method $this nlike($field, $value = null, $relation = 'AND')
 * @method $this in($field, $value = null, $relation = 'AND')
 * @method $this notIn($field, $value = null, $relation = 'AND')
 * @method $this ni($field, $value = null, $relation = 'AND')
 * @method $this isNull($field, $value = null, $relation = 'AND')
 * @method $this null($field, $value = null, $relation = 'AND')
 * @method $this isNotNull($field, $value = null, $relation = 'AND')
 * @method $this notNull($field, $value = null, $relation = 'AND')
 * @method $this nnull($field, $value = null, $relation = 'AND')
 * @method $this select(...$fields)
 * @method $this from($table)
 * @method $this set(...$array)
 * @method $this values(...$array)
 * @method $this where(...$array)
 * @method $this group(...$array)
 * @method $this groupBy(...$array)
 * @method $this having(...$array)
 * @method $this order(...$array)
 * @method $this orderBy(...$array)
 * @method $this limit(int $skip, int $per = null)
 */
abstract class Model
{
    /**
     * @var array maping the function name and the operator, to build Expressions in WHERE condition.
     * <pre>user can call it like this:
     *      $user->notNull()->eq('id', 1);
     * will create Expressions can explain to SQL:
     *      WHERE user.id IS NOT NULL AND user.id = 1</pre>
     */
    protected static $operators = [
        'equal' => '', 'eq' => '',
        'notEqual' => '[!]', 'ne' => '[!]',
        'greaterThan' => '[>]', 'gt' => '[>]',
        'lessThan' => '[<]', 'lt' => '[<]',
        'greaterThanOrEqual' => '[>=]', 'ge' => '[>=]', 'gte' => '[>=]',
        'lessThanOrEqual' => '[<=]', 'le' => '[<=]', 'lte' => '[<=]',
        'between' => '[<>]', 'bt' => '[<>]',
        'notBetween' => '[><]', 'nbt' => '[><]', 'nb' => '[><]',
        'like' => '[~]',
        'notLike' => '[!~]', 'nlike' => '[!~]',
        'in' => '',
        'notIn' => '[!]', 'ni' => '[!]',
        'isNull' => '', 'null' => '',
        'isNotNull' => '[!]', 'notNull' => '[!]', 'nnull' => '[!]',
    ];

    /**
     * @var array Part of SQL, maping the function name and the operator to build SQL Part.
     * <pre>call function like this:
     *      $user->order('id DESC', 'name ASC')->limit(2,1);
     *  can explain to SQL:
     *      ORDER BY id DESC, name ASC LIMIT 2,1</pre>
     */
    protected static $sqlParts = [
        'select' => 'SELECT',
        'from' => 'FROM',
        'table' => 'FROM',
        'set' => 'SET',
        'values' => 'VALUES',
        'where' => 'WHERE',
        'group' => 'GROUP', 'groupBy' => 'GROUP',
        'having' => 'HAVING',
        'order' => 'ORDER', 'orderBy' => 'ORDER',
        'limit' => 'LIMIT',
    ];

    /**
     * @var array Stored the Expressions of the SQL.
     */
    protected $sql = [];

    /**
     * @var string  The table name in database.
     */
    protected $table;

    /**
     * @var string  The primary key of this ORM, just suport single primary key.
     */
    protected $primary = 'id';

    /**
     * @var array Stored the attributes of the current object
     */
    protected $data = [];

    /**
     * @var array Stored the drity data of this object, when call "insert" or "update" function, will write this data into database.
     */
    protected $dirty = [];

    /**
     * @var array Stored the configure of the relation, or target of the relation.
     */
    protected $relations = [];

    public const BELONGS_TO = 'BELONGS_TO',
        HAS_MANY = 'HAS_MANY',
        HAS_ONE = 'HAS_ONE';

    public function __construct(array $config = [])
    {
        foreach ($config as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * function to reset the  $sql
     *
     * @return self return $this, can using chain method calls.
     */
    public function reset()
    {
        $this->sql = [];

        return $this;
    }

    /**
     * function to SET or RESET the dirty data.
     *
     * @param array $dirty The dirty data will be set, or empty array to reset the dirty data.
     *
     * @return self return $this, can using chain method calls.
     */
    public function dirty(array $dirty = [])
    {
        $this->dirty = $dirty;
        $this->data = \array_merge($this->data, $dirty);

        return $this;
    }

    private function defaultTable()
    {
        if (!isset($this->sql['FROM'])) {
            $this->sql['FROM'] = $this->table;
        }
    }

    /**
     * function to find one record and assign in to current object.
     *
     * @param int $id If call this function using this param, will find record by using this id. If not set, just find
     *                the first record in database.
     *
     * @return bool|self if find record, assign in to current object and return it, other wise return "false".
     */
    public function get($id = null)
    {
        if ($id !== null) {
            $this->eq($this->primary, $id);
        }

        $this->defaultTable();
        DB::get($this->sql, PDO::FETCH_INTO, $this->reset());

        return $this->dirty();
    }

    /**
     * function to find all records in database.
     *
     * @return array return array of ORM
     */
    public function all()
    {
        $this->defaultTable();
        $sql = $this->sql;
        $this->reset();

        return DB::select($sql, PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, static::class);
    }

    /**
     * function to delete current record in database.
     *
     * @return bool
     */
    public function delete()
    {
        $this->defaultTable();
        $this->eq($this->primary, $this->__get($this->primary));

        return DB::delete($this->sql);
    }

    /**
     * function to build update SQL, and update current record in database, just write the dirty data into database.
     *
     * @return bool|self if update success return current object, other wise return false.
     */
    public function update()
    {
        if ($this->dirty === []) {
            return true;
        }

        $this->defaultTable();
        $this->sql['SET'] = $this->dirty;
        $this->eq($this->primary, $this->__get($this->primary));

        if (DB::update($this->sql)) {
            return $this->dirty()->reset();
        }

        return false;
    }

    /**
     * function to build insert SQL, and insert current record into database.
     *
     * @return bool|self if insert success return current object, other wise return false.
     */
    public function insert()
    {
        if (\count($this->dirty) === 0) {
            return true;
        }

        $this->defaultTable();
        $this->sql['VALUES'] = $this->dirty;

        if ($return = DB::insert($this->sql)) {
            $this->__set($this->primary, $return);

            return $this->dirty()->reset();
        }

        return false;
    }

    /**
     * helper function to get relation of this object.
     * There was three types of relations: {BELONGS_TO, HAS_ONE, HAS_MANY}
     *
     * @param string $name The name of the relation, the array key when defind the relation.
     *
     * @return mixed
     * @throws ModelException
     */
    protected function getRelation($name)
    {
        $relation = $this->relations[$name];
        if ($relation instanceof self || (\is_array($relation) && $relation[0] instanceof self)) {
            return $relation;
        }

        $class = 'App\\Model\\' . $relation[1];
        /** @var Model $model */
        $model = new $class();

        switch ($relation[0]) {
            case self::HAS_ONE:
                $this->relations[$name] = $model->eq($relation[2], $this->__get($this->primary))->get();
                break;
            case self::HAS_MANY:
                $this->relations[$name] = $model->eq($relation[2], $this->__get($this->primary))->select();
                break;
            case self::BELONGS_TO:
                $this->relations[$name] = $model->get($this->__get($relation[2]));
                break;
            default:
                throw new ModelException("Relation $name not found.");
        }

        return $this->relations[$name];
    }

    /**
     * magic function to make calls witch in function mapping stored in $operators and $sqlPart.
     * also can call function of PDO object.
     *
     * @param string $name function name
     * @param array  $args The arguments of the function.
     *
     * @return mixed Return the result of callback or the current object to make chain method calls.
     *
     * @throws ModelException
     */
    public function __call($name, $args)
    {
        if (isset(self::$operators[$name])) {
            $value = $this->operatorValue($name, $args[1] ?? null);
            $relativity = $args[2] ?? 'AND';
            $this->addWhere($args[0] . self::$operators[$name], $value,
                ('or' === $relativity || 'OR' === $relativity) ? 'OR' : 'AND'
            );
        } elseif (isset(self::$sqlParts[$name])) {
            $this->sql[$name] = $args;
        } else {
            throw new ModelException("Method $name not exist.");
        }

        return $this;
    }

    private function operatorValue($name, $value)
    {
        switch ($name) {
            case 'equal':
            case 'eq':
                if (\is_array($value)) {
                    $value = Json::encode($value);
                }
                break;

            case 'between':
            case 'bt':
            case 'notBetween':
            case 'nbt':
            case 'nb':
                if (\is_array($value) && isset($value[0]) && !isset($value[1])) {
                    $value = $value[0];
                }
                break;

            case 'in':
            case 'notIn':
            case 'ni':
                $value = (array) $value;
                break;

            case 'isNull':
            case 'null':
            case 'isNotNull':
            case 'notNull':
            case 'nnull':
                $value = null;
                break;
        }

        return $value;
    }

    /**
     * helper function to add condition into WHERE.
     * create the SQL Expressions.
     *
     * @param string $field The field name, the source of Expressions
     * @param mixed  $value the target of the Expressions
     * @param string $op    the operator to concat this Expressions into WHERE or SET statment.
     */
    public function addWhere($field, $value, $op = 'AND')
    {
        if (!isset($this->sql['WHERE'])) {
            $this->sql['WHERE'] = [];
        }

        if (isset($this->sql['WHERE'][$op])) {
            $this->sql['WHERE'][$op][$field] = $value;
        } else {
            $this->sql['WHERE'][$field] = $value;
            $this->sql['WHERE'][$op] = $this->sql['WHERE'];
        }
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        $this->dirty[$name] = $this->data[$name] = $value;
    }

    /**
     * @param string $name
     */
    public function __unset($name)
    {
        if (isset($this->data[$name])) {
            unset($this->data[$name]);
        }
        if (isset($this->dirty[$name])) {
            unset($this->dirty[$name]);
        }
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function __get($name)
    {
        if (isset($this->relations[$name])) {
            return $this->getRelation($name);
        }

        return $this->data[$name] ?? null;
    }
}
