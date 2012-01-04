<?php

namespace ext\activedocument\drivers\riak;

use \Yii;

Yii::setPathOfAlias('riiak', Yii::getPathOfAlias('ext.activedocument.vendors.riiak'));

/**
 * @property string $host
 * @property string|int $port
 * @property bool $ssl
 * @property string $prefix
 * @property string $mapredPrefix
 * @property string $clientId
 * @property int $r
 * @property int $w
 * @property int $dw
 * @property bool $enableProfiling
 */
class Adapter extends \ext\activedocument\Adapter {
    
    /**
     * @var drivers\riak\Adaptor Instance of drivers\riak\Adaptor class 
     */
    public static $_objInstance;

    /**
     * @param array|null $attributes optional
     * @return \riiak\Riiak
     */
    protected function loadStorageInstance(array $attributes = null) {
        $storageInstance = new \riiak\Riiak;
        if (!empty($attributes))
            foreach ($attributes as $key => $value)
                $storageInstance->$key = $value;
        $storageInstance->init();
        return $storageInstance;
    }

    /**
     * @param string $name
     * @return \ext\activedocument\drivers\riak\Container
     */
    protected function loadContainer($name) {
        return new Container($this, $name);
    }

    /**
     * @param bool $reset
     * @return \riiak\MapReduce
     */
    public function getMapReduce($reset = false) {
        return $this->_storageInstance->getMapReduce($reset);
    }
    
    /**
     * @param bool $reset
     * @return \riiak\SecondaryIndexes
     */
    public function getSecondaryIndexObject($reset = false) {
        return $this->_storageInstance->getSecondaryIndexObject($reset);
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     * @return int
     */
    protected function countInternal(\ext\activedocument\Criteria $criteria) {
        $mr = $this->applySearchFilters($criteria);

        $mr->map('function(){return [1];}');
        $mr->reduce('Riak.reduceSum');
        $result = $mr->run();
        $result = array_shift($result);
        return $result;
    }
    
    /**
     * Method to change getObjects() method response.
     * 
     * @param array $data
     * @return array
     */
    public function getObjectsData($data){
        /*
         * Check if data array is not empty.
         */
        if(empty($data))
            return;
        /*
         * Declare result data array and index.
         */
        $resultData = array();
        $index = 0;
        $valueIndex = 0;
        /*
         * Prepare loop to generate result array.
         */
        foreach($data as $key => $value){
            $resultData[$index]['bucket'] = $value->data['bucket'];
            $resultData[$index]['key'] = $value->data['key'];
            $resultData[$index]['values'][$valueIndex]['data'] = json_encode($value->data);
            $resultData[$index]['values'][$valueIndex]['metadata'] = array();
            $index++;
        }
        /*
         * Return result data array.
         */
        return $resultData;
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     * @return array[]\ext\activedocument\drivers\riak\Object
     */
    protected function findInternal(\ext\activedocument\Criteria $criteria) {
        $mr = $this->applySearchFilters($criteria);
        //$criteria->search = Array ( '0' => Array ( "column" => lastName, "keyword" => "L", "like" => 1, "escape" => 1 ) );
        /**
         * Check search criteria is specified or not to fetch data using secondary indexes.
         * @todo - As Secondary indexs support for only one key search, added second condition
         */
        if(!empty($criteria->search) ) {
                /**
                 * Check if useSecondaryIndex flag is set to true and storage engine supports leveldb.
                 * @todo In Progress- working on implementing sorting and pagination task.
                 */
                if ($this->_storageInstance->_useSecondaryIndex && $this->_storageInstance->getIsSecondaryIndexSupport()) {
                    Yii::trace("Using secondary Indexes", "ext.activedocument.drivers.riak");
                    $result = array();
                    $resultObjectData = array();
                    /**
                     * Get container
                     */
                    $container = $this->getContainer($mr->inputs);
                    /**
                     * Get secondary index class object
                     */
                    $objSecondaryIndex = $this->getSecondaryIndexObject(true);
                    /**
                     * Get list of keys using search criteria
                     */
                    $arrKeys = $objSecondaryIndex->getKeys($criteria);
                    $resultObjectData = array();
                    /**
                     * Check for empty search keys
                     */
                    if(0 < count($arrKeys['keys'])){
                        /**
                         * Set search criteria for Map/Reduce
                         */
                        $criteria->inputs = $objSecondaryIndex->prepareInputKeys($arrKeys['keys'], $criteria->container);
                        /**
                         * Update Map/Reduce criteria using list of keys
                         */
                        unset($mr);
                        $mr = $this->applySearchFilters($criteria);
                    }else{
                        /**
                         * If key list is empty show no records found
                         */
                       return array();
                    }
                }
        }
        
        /**
         * If no phases are to be run, skip m/r and perform async object fetch
         * @todo With a small data subset, performance is roughly equal to m/r, need to
         * test large set of data
         *
         * @todo Disabling, as this doesn't account for sorting & pagination
         */
        if(empty($mr->phases)){
            $result = array();
            $resultObjectData = array();
            $container = $this->getContainer($mr->inputs);
            if($mr->inputMode=='bucket') {
                $result = $container->getObjects($container->getKeys());
                $resultObjectData = $this->getObjectsData($result);
                $objects = array_map(array($this, 'populateObject'), $resultObjectData);
                /**
                 * @todo Disable this functionality because of it is not working for pagination and sorting.
                 */
                //return $objects;
            } else {
                $result = $container->getObjects(array_map(function($input)use(&$container){
                        if(empty($container))
                            $container = $this->getContainer($input['container']);
                        return $input['key'];
                    },$criteria->inputs));
                $resultObjectData = $this->getObjectsData($result);
                $objects = array_map(array($this, 'populateObject'), $resultObjectData);
                /**
                 * @todo Disable this functionality because of it is not working for pagination and sorting.
                 */
                //return $objects;
            }
        }
        $mr->map('function(value){return [value];}');

        /**
         * Apply default sorting
         */
        if (!empty($criteria->order)) {
            $orderBy = explode(',', $criteria->order);
            foreach ($orderBy as $order) {
                preg_match('/(?:(\w+)\.)?(\w+)(?:\s+(ASC|DESC))?/', trim($order), $matches);
                $field = $matches[2];
                $desc = (isset($matches[3]) && strcasecmp($matches[3], 'desc') === 0);
                $mr->reduce('Riak.reduceSort', array('arg' => '
                function(a,b){
                    var field = "' . $field . '";
                    var str1 = Riak.mapValuesJson(' . ($desc ? 'b' : 'a') . ')[0];
                    var str2 = Riak.mapValuesJson(' . ($desc ? 'a' : 'b') . ')[0];

                    if (((typeof str1 === "undefined" || str1 === null) ? undefined :
                    str1[field]) < ((typeof str2 === "undefined" || str2 === null) ? undefined :
                    str2[field])) {
                        return -1;
                    } else if (((typeof str1 === "undefined" || str1 === null) ? undefined :
                    str1[field]) === ((typeof str2 === "undefined" || str2 === null) ? undefined :
                    str2[field])) {
                        return 0;
                    } else if (((typeof str1 === "undefined" || str1 === null) ? undefined :
                    str1[field]) > ((typeof str2 === "undefined" || str2 === null) ? undefined :
                    str2[field])) {
                        return 1;
                    }
                }'));
            }
        }

        if ($criteria->limit > 0) {
            $offset = $criteria->offset > 0 ? $criteria->offset : 0;
            
            $sliceCriteria = array(
                'sort' => 'function(a, b){if (a < b) {return -1;} else if (a === b) {return 0;} else if (a > b) {return 1;}}',
                'slice' => array($offset, $offset + $criteria->limit),
                'reduce_phase_only_1' => true
            );
            
            /**
             * Set default sorting criteria if sort order is empty
             */
            $sortFunction = "values";
            if(empty($criteria->order))
                $sortFunction = "Riak.reduceSort(values, arg['sort'])";
            
            $mr->reduce("function(values,arg){return Riak.reduceSlice($sortFunction, arg['slice']);}", array('arg' => $sliceCriteria));
        }

        /**
         * Filter not found
         */
        $results = array_filter($mr->run(), function($r) {
                    return!array_key_exists('not_found', $r);
                });

        $objects = array();
        if (!empty($results))
            $objects = array_map(array($this, 'populateObject'), $results);
        return $objects;
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     * @return \riiak\MapReduce
     */
    protected function applySearchFilters(\ext\activedocument\Criteria $criteria) {
        $mr = $this->getMapReduce(true);
        
        $mode = null;
        if (!empty($criteria->inputs))
            foreach ($criteria->inputs as $input)
                if (empty($input['key']) && (!$mode || $mode == 'bucket')) {
                    if (!$mode)
                        $mode = 'bucket';
                    $mr->addBucket($input['container']);
                }elseif (!$mode || $mode == 'input') {
                    if (!$mode)
                        $mode = 'input';
                    $mr->addBucketKeyData($input['container'], $input['key'], $input['data']);
                }

        if (!empty($criteria->container) && (!$mode || $mode == 'bucket'))
            $mr->addBucket($criteria->container);

        /**
         * Filter non-existent results
         */
        /* $mr->map('
          function(value){
          if(!value["not_found"]) {
          return [[value.bucket,value.key]];
          } else {
          return [];
          }
          }
          '); */

        if (!empty($criteria->phases))
            foreach ($criteria->phases as $phase)
                $mr->addPhase($phase['phase'], $phase['function'], $phase['args']);

        /*
          if (!empty($criteria->params)) {
          foreach ($criteria->params as key=>value) {
          $mr->map('
          function(value){
          if(!value["not_found"]) {
          var object = Riak.mapValuesJson(value)[0];
          if(' . $key . '=='.$value.'/))) {
          return [[value.bucket,value.key]];
          }
          }
          return [];
          }
          ');
          }
         */
        if (0 < count($criteria->inputs)) { 
            $mr->map('
                function(value){
                    if(!value["not_found"]) {
                        var object = Riak.mapValuesJson(value)[0];
                            return [[value.bucket,value.key]];
                    }
                    return [];
                }
            ');
        } else {
            if(!empty($criteria->search))
            foreach ($criteria->search as $column) {
                /**
                 * @todo preg_quote may not be appropriate for js regex
                 * @todo lowercasing the strings may not be a good idea...
                 */
                $column['keyword'] = !$column['escape'] ? : preg_quote($column['keyword'], '/');
                $mr->map('
                function(value){
                    if(!value["not_found"]) {
                        var object = Riak.mapValuesJson(value)[0];
                        var val = object["' . $column['column'] . '"].toLowerCase();
                        if(' . ($column['like'] ? '' : '!') . '(val.match(/' . strtolower($column['keyword']) . '/))) {
                            return [[value.bucket,value.key]];
                        }
                    }
                    return [];
                }
                    ');
            }

        if (!empty($criteria->columns))
            foreach ($criteria->columns as $column) {
                $mr->map('
                function(value){
                    if(!value.not_found) {
                        var object = Riak.mapValuesJson(value)[0];
                        if(object["' . $column['column'] . '"] ' . $column['operator'] . ' "' . $column['value'] . '") {
                            return [[value.bucket,value.key]];
                        }
                    }
                    return [];
                }
                    ');
            }
        }

        /**
         * @todo Implement column conditions
         */
        if (!empty($criteria->array))
            ;

        /**
         * @todo Implement "between" conditions
         */
        if (!empty($criteria->between))
            ;

        return $mr;
    }

    /**
     * @param array $arr
     * @return \ext\activedocument\drivers\riak\Object
     */
    protected function populateObject($arr) {
        return new Object($this->getContainer($arr['bucket']), $arr['key'], \CJSON::decode($arr['values'][0]['data']), true);
    }

}