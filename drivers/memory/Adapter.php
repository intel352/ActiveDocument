<?php

namespace ext\activedocument\drivers\memory;

/**
 * Adapter for Memory driver
 * 
 * @version $Version$
 * @author $Author$
 */
class Adapter extends \ext\activedocument\Adapter {

    protected function loadStorageInstance(array $attributes=null) {
        return new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
    }

    protected function loadContainer($name) {
        return new Container($this, $name);
    }

    public function count(\ext\activedocument\Criteria $criteria) {
        $results = $this->applySearchFilters($criteria);
        
        /**
         * @todo count results
         */
        return false;
    }

    public function find(\ext\activedocument\Criteria $criteria) {
        $results = $this->applySearchFilters($criteria);
        
        /**
         * Apply default sorting
         */
        if (!empty($criteria->order)) {
            $orderBy = explode(',', $criteria->order);
            foreach($orderBy as $order) {
                preg_match('/(?:(\w+)\.)?(\w+)(?:\s+(ASC|DESC))?/',trim($order),$matches);
                $field = $matches[2];
                $desc = (isset($matches[3]) && strcasecmp($matches[3], 'desc')===0);
                /**
                 * @todo apply sorting here
                 */
            }
        }
        if ($criteria->limit > 0) {
            $offset = $criteria->offset > 0 ? $criteria->offset : 0;
            /**
             * @todo limit results: $offset, $offset + $criteria->limit
             */
        }
        $objects = array();
        if (!empty($results))
            foreach ($results as $result)
                $objects[] = $this->populateObject($result);
        return $objects;
    }
    
    protected function applySearchFilters(\ext\activedocument\Criteria $criteria) {
        /**
         * @todo Search specified container
         */
        /*if (!empty($criteria->container))
            $mr->addBucket($criteria->container);*/

        /**
         * @todo Search specified containers *or* container/key combos
         */
        /*if (!empty($criteria->inputs))
            foreach ($criteria->inputs as $input)
                if (empty($input['key']))
                    $mr->addBucket($input['container']);
                else
                    $mr->addBucketKeyData($input['container'], $input['key'], $input['data']);*/
                
        /**
         * @todo Throw exception about phases not being supported for memory
         */
        /*if (!empty($criteria->phases))
            foreach ($criteria->phases as $phase)
                $mr->addPhase($phase['phase'], $phase['function'], $phase['args']);*/
        
        if(!empty($criteria->search))
            foreach($criteria->search as $column) {
                /**
                 * @todo preg_quote may not be appropriate for js regex
                 * @todo lowercasing the strings may not be a good idea...
                 */
                $column['keyword'] = !$column['escape'] ?: preg_quote($column['keyword'],'/');
                /**
                 * @todo search object columns for keyword (regex match)
                 */
            }
        
        if(!empty($criteria->columns))
            foreach($criteria->columns as $column) {
                /**
                 * @todo compare column values via operator
                 */
            }
        
        /**
         * @todo Implement column conditions
         */
        if(!empty($criteria->array))
            ;
        
        /**
         * @todo Implement "between" conditions
         */
        if(!empty($criteria->between))
            ;
        
        /**
         * @todo results
         */
    }

    protected function populateObject($arr) {
        return new Object($this->getContainer($arr['container']), $arr['key'], $arr['data'], true);
    }

}