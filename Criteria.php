<?php

namespace ext\activedocument;

use \CComponent;

class Criteria extends CComponent {

    public $container;
    public $inputs = array();
    public $phases = array();
    public $params = array();
    /**
     * Search conditions
     * array(
     *  array('column' => $column, 'keyword' => $keyword, 'like' => $like, 'escape' => $escape)
     * )
     *
     * @var array
     */
    public $search = array();
    /**
     * Column conditions
     * array(
     *  array('column' => $name, 'value' => $value, 'operator' => $operator)
     * )
     *
     * @var array
     */
    public $columns = array();
    /**
     * Array conditions (column [not] in array)
     * array(
     *  array('column' => $column, 'values' => $values, 'like' => $like)
     * )
     *
     * @var array
     */
    public $array = array();
    /**
     * Between conditions
     * array(
     *  array('column' => $column, 'valueStart' => $valueStart, 'valueEnd' => $valueEnd)
     * )
     *
     * @var array
     */
    public $between = array();
    /**
     * @var integer maximum number of records to be returned. If less than 0, it means no limit.
     */
    public $limit = -1;
    /**
     * @var integer zero-based offset from where the records are to be returned. If less than 0, it means starting from the beginning.
     */
    public $offset = -1;
    /**
     * @var string how to sort the query results. This refers to the ORDER BY clause in an SQL statement.
     */
    public $order = '';

    /**
     * @param array $data Array criteria to initialize Criteria object
     */
    public function __construct(array $data = array()) {
        if (!empty($data))
            foreach ($data as $name => $value)
                $this->$name = $value;
    }

    /**
     * Add input to Criteria object, used for map or reduce phases, etc
     *
     * @param string $container
     * @param string $key  optional
     * @param mixed  $data optional
     *
     * @return \ext\activedocument\Criteria
     */
    public function addInput($container, $key = null, $data = null) {
        $this->inputs[] = array('container' => $container, 'key' => $key, 'data' => $data);
        return $this;
    }

    /**
     * Adds a map phase
     *
     * @param string $function The map function that will be evaluated during the query execution
     * @param array  $args     optional
     *
     * @return \ext\activedocument\Criteria
     */
    public function addMapPhase($function, $args = array()) {
        return $this->addPhase('map', $function, $args);
    }

    /**
     * Adds a reduce phase
     *
     * @param string $function The reduce function that will be evaluated during the query execution
     * @param array  $args     optional
     *
     * @return \ext\activedocument\Criteria
     */
    public function addReducePhase($function, $args = array()) {
        return $this->addPhase('reduce', $function, $args);
    }

    /**
     * Generic method to add phase
     *
     * @param string $phase    Phase type that is being added
     * @param string $function The function that will be evaluated during the query execution
     * @param array  $args     optional
     *
     * @return \ext\activedocument\Criteria
     */
    public function addPhase($phase, $function, $args = array()) {
        $this->phases[] = array('phase' => $phase, 'function' => $function, 'args' => $args);
        return $this;
    }

    /**
     * Merge the current Criteria object with another Criteria instance or array
     *
     * @param array|\ext\activedocument\Criteria $criteria Array or criteria object
     *
     * @return \ext\activedocument\Criteria
     */
    public function mergeWith($criteria) {
        if (is_array($criteria))
            $criteria = new self($criteria);

        if ($criteria->container !== null)
            $this->container = $criteria->container;

        foreach (array('inputs', 'phases', 'params', 'search', 'columns', 'array', 'between') as $arr)
            $this->$arr = array_merge((array)$this->$arr, (array)$criteria->$arr);

        if ($criteria->limit > 0)
            $this->limit = $criteria->limit;

        if ($criteria->offset >= 0)
            $this->offset = $criteria->offset;

        if ($this->order !== $criteria->order) {
            if ($this->order === '')
                $this->order = $criteria->order;
            else if ($criteria->order !== '')
                $this->order = $criteria->order . ', ' . $this->order;
        }

        return $this;
    }

    /**
     * Adds a condition to search a column for existence of the provided keyword.
     * This is not ideal for exact match searching.
     *
     * @param string $column
     * @param string $keyword
     * @param bool   $escape optional
     * @param bool   $like   optional
     *
     * @return \ext\activedocument\Criteria
     */
    public function addSearchCondition($column, $keyword, $escape = true, $like = true) {
        if ($keyword == '')
            return $this;
        $this->search[] = array('column' => $column, 'keyword' => $keyword, 'like' => $like, 'escape' => $escape);
        return $this;
    }

    /**
     * Adds a condition to compare columns by the associated values
     *
     * @param array  $columns  Array with format of column => value
     * @param string $operator optional
     *
     * @return \ext\activedocument\Criteria
     */
    public function addColumnCondition(array $columns, $operator = '==') {
        foreach ($columns as $name => $value)
            $this->columns[] = array('column' => $name, 'value' => $value, 'operator' => $operator);
        return $this;
    }

    /**
     * Adds a condition to check a column against multiple possible values.
     * Similar to a SQL "IN" statement.
     *
     * @param string $column
     * @param array  $values
     * @param bool   $like optional
     *
     * @return \ext\activedocument\Criteria
     */
    public function addArrayCondition($column, $values, $like = true) {
        if (count($values) === 1) {
            $value = reset($values);
            return $this->addColumnCondition(array($column => $value), $like);
        }
        $this->array[] = array('column' => $column, 'values' => $values, 'like' => $like);
        return $this;
    }

    /**
     * Adds a condition to find a column whose value is between two specified values
     *
     * @param string $column
     * @param mixed  $valueStart
     * @param mixed  $valueEnd
     *
     * @return \ext\activedocument\Criteria
     */
    public function addBetweenCondition($column, $valueStart, $valueEnd) {
        if ($valueStart === '' || $valueEnd === '')
            return $this;
        $this->between[] = array('column' => $column, 'valueStart' => $valueStart, 'valueEnd' => $valueEnd);
        return $this;
    }

    /**
     * Adds a condition to compare a column by a specified value, allows partial matching.
     *
     * @param string $column
     * @param mixed  $value
     * @param bool   $partialMatch optional
     * @param bool   $escape       optional
     *
     * @return \ext\activedocument\Criteria
     */
    public function compare($column, $value, $partialMatch = false, $escape = true) {
        if (is_array($value)) {
            if ($value === array())
                return $this;
            return $this->addArrayCondition($column, $value);
        }
        else
            $value = "$value";

        if (preg_match('/^(?:\s*(<>|<=|>=|<|>|==|===|!=|!==))?(.*)$/', $value, $matches)) {
            $value    = $matches[2];
            $operator = $matches[1];
        }
        else
            $operator = '';

        if ($value === '')
            return $this;

        if ($partialMatch) {
            if ($operator === '')
                return $this->addSearchCondition($column, $value, $escape);
            if ($operator === '<>')
                return $this->addSearchCondition($column, $value, $escape, false);
        }
        else if ($operator === '')
            $operator = '==';

        $this->addColumnCondition(array($column => $value), $operator);

        return $this;
    }

    /**
     * Exports Criteria object as an array
     *
     * @return array
     */
    public function toArray() {
        $result = array();
        foreach (array('inputs', 'phases', 'params', 'search', 'columns', 'array', 'between', 'container', 'limit', 'offset', 'order') as $name)
            $result[$name] = $this->$name;
        return $result;
    }

}