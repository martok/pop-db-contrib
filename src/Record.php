<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Db;

/**
 * Record class
 *
 * @category   Pop
 * @package    Pop_Db
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.1.1
 */
class Record implements \ArrayAccess
{

    /**
     * Data set result constants
     * @var string
     */
    const ROW_AS_ARRAY       = 'ROW_AS_ARRAY';
    const ROW_AS_ARRAYOBJECT = 'ROW_AS_ARRAYOBJECT';
    const ROW_AS_RECORD      = 'ROW_AS_RECORD';

    /**
     * Database connection(s)
     * @var array
     */
    protected static $db = ['default' => null];

    /**
     * SQL Object
     * @var Sql
     */
    protected $sql = null;

    /**
     * Table name
     * @var string
     */
    protected $table = null;

    /**
     * Table prefix
     * @var string
     */
    protected $prefix = null;

    /**
     * Result rows (an array of arrays)
     * @var array
     */
    protected $rows = [];

    /**
     * Columns of the first result row
     * @var string
     */
    protected $columns = [];

    /**
     * Row gateway
     * @var Gateway\Row
     */
    protected $rowGateway = null;

    /**
     * Table gateway
     * @var Gateway\Table
     */
    protected $tableGateway = null;

    /**
     * Primary keys
     * @var array
     */
    protected $primaryKeys = ['id'];

    /**
     * Is new record flag
     * @var boolean
     */
    protected $isNew = false;

    /**
     * Constructor
     *
     * Instantiate the database record object.
     *
     * Optional parameters are an array of values, db adapter,
     * or a table name
     *
     * @throws Exception
     * @return Record
     */
    public function __construct()
    {
        $args    = func_get_args();
        $columns = null;
        $table   = null;
        $db      = null;

        foreach ($args as $arg) {
            if (is_array($arg) || ($arg instanceof \ArrayAccess) || ($arg instanceof \ArrayObject)) {
                $columns = $arg;
            } else if ($arg instanceof Adapter\AbstractAdapter) {
                $db = $arg;
            } else if (is_string($arg)) {
                $table = $arg;
            }
        }

        if (null !== $db) {
            $class = get_class($this);
            $class::setDb($db);
        }

        if (!static::hasDb()) {
            throw new Exception('Error: A database connection has not been set.');
        }

        if (null !== $table) {
            $this->setTable($table);
        }

        // Set the table name from the class name
        if (null === $this->table) {
            $this->setTableFromClassName(get_class($this));
        } else {
            $this->setSql(new Sql(static::db(), $this->getFullTable()));
            $this->rowGateway   = new Gateway\Row($this->sql, $this->primaryKeys, $this->getFullTable());
            $this->tableGateway = new Gateway\Table($this->sql, $this->getFullTable());
        }

        if (null !== $columns) {
            $this->isNew = true;
            $this->setColumns($columns);
        }
    }

    /**
     * Set DB connection
     *
     * @param  Adapter\AbstractAdapter $db
     * @param  string                  $prefix
     * @param  boolean                 $isDefault
     * @return void
     */
    public static function setDb(Adapter\AbstractAdapter $db, $prefix = null, $isDefault = false)
    {
        if (null !== $prefix) {
            static::$db[$prefix] = $db;
        }

        $class = get_called_class();
        static::$db[$class] = $db;

        if (($isDefault) || ($class === __CLASS__)) {
            static::$db['default'] = $db;
        }
    }

    /**
     * Check is the class has a DB adapter
     *
     * @return boolean
     */
    public static function hasDb()
    {
        $result = false;
        $class  = get_called_class();

        if (isset(static::$db[$class])) {
            $result = true;
        } else if (isset(static::$db['default'])) {
            $result = true;
        } else {
            foreach (static::$db as $prefix => $adapter) {
                if (substr($class, 0, strlen($prefix)) == $prefix) {
                    $result = true;
                }
            }
        }

        return $result;
    }

    /**
     * Get DB adapter
     *
     * @throws Exception
     * @return Adapter\AbstractAdapter
     */
    public static function db()
    {
        $class = get_called_class();

        if (isset(static::$db[$class])) {
            return static::$db[$class];
        } else if (isset(static::$db['default'])) {
            return static::$db['default'];
        } else {
            $dbAdapter = null;
            foreach (static::$db as $prefix => $adapter) {
                if (substr($class, 0, strlen($prefix)) == $prefix) {
                    $dbAdapter = $adapter;
                }
            }
            if (null !== $dbAdapter) {
                return $dbAdapter;
            } else {
                throw new Exception('No database adapter was found.');
            }
        }
    }

    /**
     * Get the SQL object
     *
     * @throws Exception
     * @return Sql
     */
    public static function sql()
    {
        return (new static())->getSql();
    }

    /**
     * Set the SQL object
     *
     * @param  Sql $sql
     * @return Record
     */
    public function setSql(Sql $sql)
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * Get the SQL object
     *
     * @return Sql
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Find by ID static method
     *
     * @param  mixed  $id
     * @param  string $resultsAs
     * @return Record
     */
    public static function findById($id, $resultsAs = 'ROW_AS_RECORD')
    {
        return (new static())->findRecordById($id, $resultsAs);
    }

    /**
     * Find by static method
     *
     * @param  array  $columns
     * @param  array  $options
     * @param  string $resultsAs
     * @return Record
     */
    public static function findBy(array $columns = null, array $options = null, $resultsAs = 'ROW_AS_RECORD')
    {
        return (new static())->findRecordsBy($columns, $options, $resultsAs);
    }

    /**
     * Find all static method
     *
     * @param  array  $options
     * @param  string $resultsAs
     * @return Record
     */
    public static function findAll(array $options = null, $resultsAs = 'ROW_AS_RECORD')
    {
        return static::findBy(null, $options, $resultsAs);
    }

    /**
     * Static method to execute a custom prepared SQL statement.
     *
     * @param  mixed  $sql
     * @param  mixed  $params
     * @param  string $resultsAs
     * @return Record
     */
    public static function execute($sql, $params, $resultsAs = 'ROW_AS_RECORD')
    {
        return (new static())->executeStatement($sql, $params, $resultsAs);
    }

    /**
     * Static method to execute a custom SQL query.
     *
     * @param  mixed  $sql
     * @param  string $resultsAs
     * @return Record
     */
    public static function query($sql, $resultsAs = 'ROW_AS_RECORD')
    {
        return (new static())->executeQuery($sql, $resultsAs);
    }

    /**
     * Static method to get the total count of a set from the DB table
     *
     * @param  array  $columns
     * @param  string $resultsAs
     * @return int
     */
    public static function getTotal(array $columns = null, $resultsAs = 'ROW_AS_RECORD')
    {
        $record = new static();
        $params = null;
        $where  = null;

        if (null !== $columns) {
            $parsedColumns = static::parseColumns($columns, $record->getSql()->getPlaceholder());
            $params = $parsedColumns['params'];
            $where  = $parsedColumns['where'];
        }

        $record->tg()->select(['total_count' => 'COUNT(1)'], $where, $params);
        $record->setRows($record->tg()->rows(), $resultsAs);

        return (int)$record->total_count;
    }

    /**
     * Find record by ID method
     *
     * @param  mixed  $id
     * @param  string $resultsAs
     * @return Record
     */
    public function findRecordById($id, $resultsAs = 'ROW_AS_RECORD')
    {
        $this->rg()->find($id);
        $this->setColumns($this->rg()->getColumns(), $resultsAs);

        return $this;
    }

    /**
     * Find records by method
     *
     * @param  array  $columns
     * @param  array  $options
     * @param  string $resultsAs
     * @return Record
     */
    public function findRecordsBy(array $columns = null, array $options = null, $resultsAs = 'ROW_AS_RECORD')
    {
        $params = null;
        $where  = null;

        if (null !== $columns) {
            $parsedColumns = static::parseColumns($columns, $this->getSql()->getPlaceholder());
            $params = $parsedColumns['params'];
            $where  = $parsedColumns['where'];
        }

        $this->tg()->select(null, $where, $params, $options);
        $this->setRows($this->tg()->rows(), $resultsAs);

        return $this;
    }

    /**
     * Find all records method
     *
     * @param  array  $options
     * @param  string $resultsAs
     * @return Record
     */
    public function findAllRecords(array $options = null, $resultsAs = 'ROW_AS_RECORD')
    {
        return $this->findRecordsBy(null, $options, $resultsAs);
    }

    /**
     * Method to execute a custom prepared SQL statement.
     *
     * @param  mixed  $sql
     * @param  mixed  $params
     * @param  string $resultsAs
     * @return Record
     */
    public function executeStatement($sql, $params, $resultsAs = 'ROW_AS_RECORD')
    {
        if ($sql instanceof Sql) {
            $sql = (string)$sql;
        }
        if (!is_array($params)) {
            $params = [$params];
        }

        $db = static::db();
        $db->prepare($sql)
           ->bindParams($params)
           ->execute();

        if (strtoupper(substr($sql, 0, 6)) == 'SELECT') {
            $rows = $db->fetchResult();
            foreach ($rows as $i => $row) {
                $rows[$i] = $row;
            }
            $this->setRows($rows, $resultsAs);
        }

        return $this;
    }

    /**
     * Method to execute a custom SQL query.
     *
     * @param  mixed  $sql
     * @param  string $resultsAs
     * @return Record
     */
    public function executeQuery($sql, $resultsAs = 'ROW_AS_RECORD')
    {
        if ($sql instanceof Sql) {
            $sql = (string)$sql;
        }

        $db = static::db();
        $db->query($sql);

        if (strtoupper(substr($sql, 0, 6)) == 'SELECT') {
            $rows = [];
            while (($row = $db->fetch())) {
                $rows[] = $row;
            }
            $this->setRows($rows, $resultsAs);
        }

        return $this;
    }

    /**
     * Method to get the total count of a set from the DB table
     *
     * @param  array  $columns
     * @param  string $resultsAs
     * @return int
     */
    public function getTotalRecords(array $columns = null, $resultsAs = 'ROW_AS_RECORD')
    {
        $params = null;
        $where  = null;

        if (null !== $columns) {
            $parsedColumns = static::parseColumns($columns, $this->getSql()->getPlaceholder());
            $params = $parsedColumns['params'];
            $where  = $parsedColumns['where'];
        }

        $this->tg()->select(['total_count' => 'COUNT(1)'], $where, $params);
        $this->setRows($this->tg()->rows(), $resultsAs);

        return (int)$this->total_count;
    }

    /**
     * Set all the table column values at once.
     *
     * @param  mixed  $columns
     * @param  string $resultsAs
     * @throws Exception
     * @return Record
     */
    public function setColumns($columns = null, $resultsAs = 'ROW_AS_RECORD')
    {
        // If null, clear the columns.
        if (null === $columns) {
            $this->columns = [];
            $this->rows    = [];
        // Else, if an array, set the columns.
        } else if (is_array($columns) || ($columns instanceof \ArrayObject)) {
            $this->columns = (array)$columns;
            switch ($resultsAs) {
                case self::ROW_AS_ARRAY:
                    $this->rows[0] = $this->columns;
                    break;
                case self::ROW_AS_ARRAYOBJECT:
                    $this->rows[0] = new \ArrayObject($this->columns, \ArrayObject::ARRAY_AS_PROPS);
                    break;
                default:
                    $this->rows[0] = $this;
            }
        } else {
            throw new Exception('The parameter passed must be either an array or null.');
        }

        return $this;
    }

    /**
     * Set all the table rows at once
     *
     * @param  array  $rows
     * @param  string $resultsAs
     * @return Record
     */
    public function setRows(array $rows = null, $resultsAs = 'ROW_AS_RECORD')
    {
        // If null, clear the rows.
        if (null === $rows) {
            $this->columns    = [];
            $this->rows       = [];
        } else {
            $this->columns = (isset($rows[0])) ? (array)$rows[0] : [];
            foreach ($rows as $row) {
                switch ($resultsAs) {
                    case self::ROW_AS_ARRAY:
                        $this->rows[] = (array)$row;
                        break;
                    case self::ROW_AS_ARRAYOBJECT:
                        $this->rows[] = new \ArrayObject((array)$row, \ArrayObject::ARRAY_AS_PROPS);
                        break;
                    default:
                        $r = new static();
                        $r->setColumns((array)$row, $resultsAs);
                        $this->rows[] = $r;
                }
            }
        }
    }

    /**
     * Set the table prefix
     *
     * @param  string $prefix
     * @return Record
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Set the table
     *
     * @param  string $table
     * @return Record
     */
    public function setTable($table)
    {
        $this->table = $table;

        $this->setSql(new Sql(static::db(), $this->getFullTable()));
        $this->rowGateway   = new Gateway\Row($this->sql, $this->primaryKeys, $this->getFullTable());
        $this->tableGateway = new Gateway\Table($this->sql, $this->getFullTable());

        return $this;
    }

    /**
     * Set the table from a class name
     *
     * @param  string $class
     * @return Record
     */
    public function setTableFromClassName($class)
    {
        if (strpos($class, '_') !== false) {
            $cls = substr($class, (strrpos($class, '_') + 1));
        } else if (strpos($class, '\\') !== false) {
            $cls = substr($class, (strrpos($class, '\\') + 1));
        } else {
            $cls = $class;
        }
        return $this->setTable(static::camelCaseToUnderscore($cls));
    }

    /**
     * Set the primary keys
     *
     * @param  array $keys
     * @return Record
     */
    public function setPrimaryKeys(array $keys)
    {
        $this->primaryKeys = $keys;
        return $this;
    }

    /**
     * Get the table prefix
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Get the table
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the full table name (prefix + table)
     *
     * @return string
     */
    public function getFullTable()
    {
        return $this->prefix . $this->table;
    }

    /**
     * Get table info and return as an array.
     *
     * @return array
     */
    public function getTableInfo()
    {
        return $this->rg()->getTableInfo();
    }

    /**
     * Get the primary keys
     *
     * @return array
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * Get the columns
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get the columns as a single array object
     *
     * @return \ArrayObject
     */
    public function getColumnsAsObject()
    {
        return new \ArrayObject($this->columns, \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Alias for getColumns
     *
     * @return array
     */
    public function toArray()
    {
        return $this->columns;
    }

    /**
     * Alias to getColumnsAsObject
     *
     * @return \ArrayObject
     */
    public function toArrayObject()
    {
        return new \ArrayObject($this->columns, \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Get the rows
     *
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * Get the rows - left in for BC
     *
     * @return array
     */
    public function getRowObjects()
    {
        return $this->rows;
    }

    /**
     * Get the rows (alias method)
     *
     * @return array
     */
    public function rows()
    {
        return $this->rows;
    }

    /**
     * Get the count of rows returned in the result
     *
     * @return int
     */
    public function count()
    {
        return count($this->rows);
    }

    /**
     * Determine if the result has rows
     *
     * @return boolean
     */
    public function hasRows()
    {
        return (count($this->rows) > 0);
    }

    /**
     * Save the record
     *
     * @param  array  $columns
     * @param  string $resultsAs
     * @return void
     */
    public function save(array $columns = null, $resultsAs = 'ROW_AS_RECORD')
    {
        // Save or update the record
        if (null === $columns) {
            $this->rg()->setColumns($this->columns);
            $this->rg()->save($this->isNew);
            $this->setRows([$this->rg()->getColumns()], $resultsAs);
        // Else, save multiple rows
        } else {
            $this->tg()->insert($columns);
            $this->setRows($this->tg()->getRows(), $resultsAs);
        }
    }

    /**
     * Delete the record or rows of records
     *
     * @param  array  $columns
     * @return void
     */
    public function delete(array $columns = null)
    {
        // Delete the record
        if (null === $columns) {
            if ((count($this->columns) > 0) && (count($this->rg()->getColumns()) == 0)) {
                $this->rg()->setColumns($this->columns);
            }
            $this->rg()->delete();
            $this->setColumns();
            if (isset($this->rows[0])) {
                unset($this->rows[0]);
            }
            if (isset($this->rowObjects[0])) {
                unset($this->rowObjects[0]);
            }
        // Delete multiple rows
        } else {
            $parsedColumns = static::parseColumns($columns, $this->getSql()->getPlaceholder());
            $this->tg()->delete($parsedColumns['where'], $parsedColumns['params']);
            $this->setRows();
        }
    }

    /**
     * Get the row gateway object
     *
     * @return Gateway\Row
     */
    protected function rg()
    {
        return $this->rowGateway;
    }

    /**
     * Get the table gateway object
     *
     * @return Gateway\Table
     */
    protected function tg()
    {
        return $this->tableGateway;
    }

    /**
     * Method to get the operator from the column name
     *
     * @param string $column
     * @return array
     */
    protected static function getOperator($column)
    {
        $op = '=';

        if (substr($column, -2) == '>=') {
            $op = '>=';
            $column = trim(substr($column, 0, -2));
        } else if (substr($column, -2) == '<=') {
            $op = '<=';
            $column = trim(substr($column, 0, -2));
        } else if (substr($column, -2) == '!=') {
            $op = '!=';
            $column = trim(substr($column, 0, -2));
        } else if (substr($column, -1) == '>') {
            $op = '>';
            $column = trim(substr($column, 0, -1));
        } else if (substr($column, -1) == '<') {
            $op = '<';
            $column = trim(substr($column, 0, -1));
        }

        return ['column' => $column, 'op' => $op];
    }


    /**
     * Method to parse the columns to create $where and $param arrays
     *
     * @param  array  $columns
     * @param  string $placeholder
     * @return array
     */
    protected static function parseColumns($columns, $placeholder)
    {
        $params = [];
        $where  = [];

        $i = 1;
        foreach ($columns as $column => $value) {
            if (!is_array($value) && (substr($value, -3) == ' OR')) {
                $value   = substr($value, 0, -3);
                $combine = ' OR';
            } else {
                $combine = null;
            }

            $operator = static::getOperator($column);
            if ($placeholder == ':') {
                $pHolder = $placeholder . $operator['column'];
            } else if ($placeholder == '$') {
                $pHolder = $placeholder . $i;
            } else {
                $pHolder = $placeholder;
            }

            // IS NULL or IS NOT NULL
            if (null === $value) {
                if (substr($column, -1) == '-') {
                    $column  = substr($column, 0, -1);
                    $where[] = $column . ' IS NOT NULL' . $combine;
                } else {
                    $where[] = $column . ' IS NULL' . $combine;
                }
            // IN or NOT IN
            } else if (is_array($value)) {
                if (substr($column, -1) == '-') {
                    $column  = substr($column, 0, -1);
                    $where[] = $column . ' NOT IN (' . implode(', ', $value) . ')' . $combine;
                } else {
                    $where[] = $column . ' IN (' . implode(', ', $value) . ')' . $combine;
                }
            // BETWEEN or NOT BETWEEN
            } else if ((substr($value, 0, 1) == '(') && (substr($value, -1) == ')') &&
                (strpos($value, ',') !== false)) {
                if (substr($column, -1) == '-') {
                    $column  = substr($column, 0, -1);
                    $where[] = $column . ' NOT BETWEEN ' . $value . $combine;
                } else {
                    $where[] = $column . ' BETWEEN ' . $value . $combine;
                }
            // LIKE or NOT LIKE
            } else if ((substr($value, 0, 2) == '-%') || (substr($value, -2) == '%-') ||
                (substr($value, 0, 1) == '%') || (substr($value, -1) == '%')) {
                $op = ((substr($value, 0, 2) == '-%') || (substr($value, -2) == '%-')) ? 'NOT LIKE' : 'LIKE';

                $where[]  = $column . ' ' . $op . ' ' .  $pHolder . $combine;
                if (substr($value, 0, 2) == '-%') {
                    $value = substr($value, 1);
                }
                if (substr($value, -2) == '%-') {
                    $value = substr($value, 0, -1);
                }
                if (isset($params[$column])) {
                    if (is_array($params[$column])) {
                        if ($placeholder == ':') {
                            $where[count($where) - 1] .= $i;
                        }
                        $params[$column][] = $value;
                    } else {
                        if ($placeholder == ':') {
                            $where[0] .= ($i - 1);
                            $where[1] .= $i;
                        }
                        $params[$column] = [$params[$column], $value];
                    }
                } else {
                    $params[$column] = $value;
                }
            // Standard operators
            } else {
                $column  = $operator['column'];
                $where[] = $column . ' ' . $operator['op'] . ' ' .  $pHolder . $combine;
                if (isset($params[$column])) {
                    if (is_array($params[$column])) {
                        if ($placeholder == ':') {
                            $where[count($where) - 1] .= $i;
                        }
                        $params[$column][] = $value;
                    } else {
                        if ($placeholder == ':') {
                            $where[0] .= ($i - 1);
                            $where[1] .= $i;
                        }
                        $params[$column] = [$params[$column], $value];
                    }
                } else {
                    $params[$column] = $value;
                }
            }

            $i++;
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * Method to convert a camelCase string to an under_score string
     *
     * @param string $string
     * @return string
     */
    protected static function camelCaseToUnderscore($string)
    {
        $strAry  = str_split($string);
        $convert = null;
        $i = 0;

        foreach ($strAry as $chr) {
            if ($i == 0) {
                $convert .= strtolower($chr);
            } else {
                $convert .= (ctype_upper($chr)) ? ('_' . strtolower($chr)) : $chr;
            }
            $i++;
        }

        return $convert;
    }

    /**
     * Magic method to set the property to the value of $this->columns[$name].
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->columns[$name] = $value;
    }

    /**
     * Magic method to return the value of $this->columns[$name].
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return (isset($this->columns[$name])) ? $this->columns[$name] : null;
    }

    /**
     * Magic method to return the isset value of $this->columns[$name].
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->columns[$name]);
    }

    /**
     * Magic method to unset $this->columns[$name].
     *
     * @param  string $name
     * @return void
     */
    public function __unset($name)
    {
        if (isset($this->columns[$name])) {
            unset($this->columns[$name]);
        }
    }

    /**
     * ArrayAccess offsetExists
     *
     * @param  mixed $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess offsetGet
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * ArrayAccess offsetSet
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @throws Exception
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess offsetUnset
     *
     * @param  mixed $offset
     * @throws Exception
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

}