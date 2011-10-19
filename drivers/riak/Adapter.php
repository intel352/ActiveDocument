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

    protected function loadStorageInstance(array $attributes = null) {
        $storageInstance = new \riiak\Riiak;
        if (!empty($attributes))
            foreach ($attributes as $key => $value)
                $storageInstance->$key = $value;
        $storageInstance->init();
        return $storageInstance;
    }

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

    public function count(\ext\activedocument\Criteria $criteria) {
        $mr = $this->applySearchFilters($criteria);

        $mr->map('function(){return [1];}');
        $mr->reduce('Riak.reduceSum');
        $result = $mr->run();
        $result = array_shift($result);
        return $result;
    }

    public function find(\ext\activedocument\Criteria $criteria) {
        $mr = $this->applySearchFilters($criteria);
        /**
         * If no phases are to be run, skip m/r and perform async object fetch
         * @todo With a small data subset, performance is roughly equal to m/r, need to
         * test large set of data
         *
         * @todo Disabling, as this doesn't account for sorting & pagination
         */
        /* if(empty($mr->phases))
          if($mr->inputMode=='bucket') {
          $container = $this->getContainer($mr->inputs);
          return $container->getObjects($container->getKeys());
          } else {
          return $container->getObjects(array_map(function($input)use(&$container){
          if(empty($container))
          $container = $this->getContainer($input['container']);
          return $input['key'];
          },$criteria->inputs));
          }
         */
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
            $mr->reduce('Riak.reduceSlice', array('arg' => array($offset, $offset + $criteria->limit)));
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

        if (!empty($criteria->search))
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

    protected function populateObject($arr) {
        return new Object($this->getContainer($arr['bucket']), $arr['key'], \CJSON::decode($arr['values'][0]['data']), true);
    }

}