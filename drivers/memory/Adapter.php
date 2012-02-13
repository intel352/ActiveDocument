<?php

namespace ext\activedocument\drivers\memory;

/**
 * Adapter for Memory driver
 */
class Adapter extends \ext\activedocument\Adapter {

    protected function loadStorageInstance(array $attributes = null) {
        return new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @param string $name
     * @return \ext\activedocument\drivers\memory\Container
     */
    protected function loadContainer($name) {
        return new Container($this, $name);
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     * @return int
     */
    protected function countInternal(\ext\activedocument\Criteria $criteria) {
        return count($this->applySearchFilters($criteria));
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     * @return array[]\ext\activedocument\drivers\memory\Object
     */
    protected function findInternal(\ext\activedocument\Criteria $criteria) {
        $values = $this->applySearchFilters($criteria);

        /**
         * Apply default sorting
         */
        if (!empty($criteria->order)) {
            $orderBy = explode(',', $criteria->order);
            foreach ($orderBy as $order) {
                preg_match('/(?:([\w\\\]+)\.)?(\w+)(?:\s+(ASC|DESC))?/', trim($order), $matches);
                $field = $matches[2];
                $desc = (isset($matches[3]) && strcasecmp($matches[3], 'desc') === 0);
                usort($values, function($a, $b) use($field, $desc) {
                    $value1 = $desc ? $b['value'] : $a['value'];
                    $value1 = ($value1 === null) ? null : $value1->$field;

                    $value2 = $desc ? $a['value'] : $b['value'];
                    $value2 = ($value2 === null) ? null : $value2->$field;

                    if ($value1 < $value2)
                        return -1;
                    elseif ($value1 === $value2)
                        return 0;
                    elseif ($value1 > $value2)
                        return 1;
                });
            }
        }

        /**
         * Apply limit
         */
        if ($criteria->limit > 0) {
            $offset = $criteria->offset > 0 ? $criteria->offset : 0;
            $values = array_slice($values, $offset, $criteria->limit);
        }

        $objects = array();
        if (!empty($values))
            foreach ($values as $value)
                $objects[] = $this->populateObject($value);
        return $objects;
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     * @return array|mixed
     */
    protected function applySearchFilters(\ext\activedocument\Criteria $criteria) {
        $values = array();

        $assignValue = function($containerName, $key, $value) {
            return array('container' => $containerName, 'key' => $key, 'value' => $value);
        };

        $buildInputs = function($containerName, array $arr) use(&$values, $assignValue) {
            array_map(function($value, $key) use($containerName, &$values, $assignValue) {
                    $values[] = $assignValue($containerName, $key, $value);
                },
                array_values($arr),
                array_keys($arr));
        };

        /**
         * Fill $objects array based on criteria-specified inputs (container = all keys, vs container/key pairs)
         */
        $mode = null;
        if (!empty($criteria->inputs))
            foreach ($criteria->inputs as $input)
                if (empty($input['key']) && (!$mode || $mode == 'container')) {
                    if (!$mode)
                        $mode = 'container';
                    $buildInputs($input['container'], (array)$this->getContainer($input['container'])->getContainerInstance()->objects);
                } elseif (!$mode || $mode == 'input') {
                    if (!$mode)
                        $mode = 'input';
                    if (array_key_exists($input['key'], (array)$this->getContainer($input['container'])->getContainerInstance()->objects))
                        $values[] = $assignValue($input['container'], $input['key'], $this->getContainer($input['container'])->getContainerInstance()->objects[$input['key']]);
                }

        if (!empty($criteria->container) && (!$mode || $mode == 'container'))
            $buildInputs($criteria->container, (array)$this->getContainer($criteria->container)->getContainerInstance()->objects);

        /**
         * Map/reduce phases, via functionality provided by PHP array_map, array_reduce
         */
        if (!empty($criteria->phases))
            foreach ($criteria->phases as $phase) {
                switch ($phase['phase']) {
                    case 'map':
                        $values = array_map($phase['function'], $values, $phase['args']);
                        break;
                    case 'reduce':
                        $values = array_reduce($values, $phase['function'], $phase['args']);
                        break;
                    /**
                     * @todo add array_filter?
                     */
                }
            }

        /**
         * Apply column searching criteria (preg match)
         */
        if (!empty($criteria->search))
            foreach ($criteria->search as $column) {
                /**
                 * @todo preg_quote may not be appropriate for js regex
                 * @todo lowercasing the strings may not be a good idea...
                 */
                $column['keyword'] = !$column['escape'] ? : preg_quote($column['keyword'], '/');
                $values = array_map(function($object) use($column) {
                    if ($object['value'] === null)
                        return null;

                    $col = strtolower($object['value']->{$column['column']});
                    if (($match = preg_match('/' . $column['keyword'] . '/i', $col)))
                        if (($column['like'] && $match) || (!$column['like'] && !$match))
                            return $object;
                    return null;
                }, $values);
            }

        /**
         * Apply column/value comparison criteria
         */
        if (!empty($criteria->columns))
            foreach ($criteria->columns as $column) {
                $values = array_map(function($object) use($column) {
                    if ($object['value'] === null)
                        return null;

                    $success = \Yii::app()->evaluateExpression('($columnValue ' . $column['operator'] . ' $userValue)',
                        array('columnValue' => $object['value']->{$column['column']}, 'userValue' => $column['value']));
                    if ($success)
                        return $object;
                    return null;
                }, $values);
            }

        /**
         * @todo Implement array (in|not in) conditions
         */
        if (!empty($criteria->array))
            ;

        /**
         * @todo Implement "between" conditions
         */
        if (!empty($criteria->between))
            ;

        /**
         * Filter out null records
         */
        $values = array_filter($values, function($var) {
            return !is_null($var);
        });

        return $values;
    }

    /**
     * @param $arr
     * @return \ext\activedocument\drivers\memory\Object
     */
    protected function populateObject($arr) {
        return new Object($this->getContainer($arr['container']), $arr['key'], $arr['value'], true);
    }

}