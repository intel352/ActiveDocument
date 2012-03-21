<?php

namespace ext\activedocument\drivers\riak;

use \Yii;

if(!Yii::getPathOfAlias('riiak')) {
    Yii::setPathOfAlias('riiak', Yii::getPathOfAlias('ext.activedocument.vendors.riiak'));
}

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
     * @param array|null $attributes optional
     *
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
     *
     * @return \ext\activedocument\drivers\riak\Container
     */
    protected function loadContainer($name) {
        return new Container($this, $name);
    }

    /**
     * @param bool $reset
     *
     * @return \riiak\MapReduce
     */
    public function getMapReduce($reset = false) {
        return $this->_storageInstance->getMapReduce($reset);
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     *
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
     *
     * @return array
     */
    public function getObjectsData($data) {
        /*
         * Check if data array is not empty.
         */
        if (empty($data))
            return;
        /*
         * Declare result data array and index.
         */
        $resultData = array();
        $index      = 0;
        $valueIndex = 0;
        /*
         * Prepare loop to generate result array.
         */
        foreach ($data as $key => $value) {
            $resultData[$index]['bucket']                          = $value->data['bucket'];
            $resultData[$index]['key']                             = $value->data['key'];
            $resultData[$index]['values'][$valueIndex]['data']     = \CJSON::encode($value->data);
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
     *
     * @return \ext\activedocument\drivers\riak\Object[]
     */
    protected function findInternal(\ext\activedocument\Criteria $criteria) {
        $mr = $this->applySearchFilters($criteria);

        $mr->map('function(value){return [value];}');

        /**
         * Apply default sorting
         */
        if (!empty($criteria->order)) {
            $orderBy = explode(',', $criteria->order);
            foreach ($orderBy as $order) {
                preg_match('/(?:([\w\\\]+)\.)?(\w+)(?:\s+(ASC|DESC))?/', trim($order), $matches);
                $field = $matches[2];
                $desc  = (isset($matches[3]) && strcasecmp($matches[3], 'desc') === 0);
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
            $sortFunction = 'values';
            if (empty($criteria->order))
                $sortFunction = 'Riak.reduceSort(values, arg["sort"])';

            $mr->reduce('function(values,arg){return Riak.reduceSlice('.$sortFunction.', arg["slice"]);}', array('arg' => $sliceCriteria));
        }

        /**
         * Filter not found
         */
        $results = array_filter($mr->run(), function($r) {
            return !array_key_exists('not_found', $r);
        });

        $objects = array();
        if (!empty($results))
            $objects = array_map(array($this, 'populateObject'), $results);
        return $objects;
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     *
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
                } elseif (!$mode || $mode == 'input') {
                    if (!$mode)
                        $mode = 'input';
                    $mr->addBucketKeyData($input['container'], $input['key'], $input['data']);
                }

        if (!empty($criteria->container) && (!$mode || $mode == 'bucket'))
            $mr->addBucket($criteria->container);

        if (!empty($criteria->phases))
            foreach ($criteria->phases as $phase)
                $mr->addPhase($phase['phase'], $phase['function'], $phase['args']);

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
            if (!empty($criteria->search))
                foreach ($criteria->search as $column) {
                    /**
                     * @todo preg_quote may not be appropriate for js regex
                     * @todo lowercasing the strings may not be a good idea...
                     */
                    $column['column'] = \CJavaScript::encode($column['column']);
                    if($column['escape'])
                        $column['keyword'] = preg_quote($column['keyword'], '/');
                    $mr->map('
                function(value){
                    if(!value["not_found"]) {
                        var object = Riak.mapValuesJson(value)[0];
                        if(object.hasOwnProperty('. $column['column'] .')) {
                            var val = object['. $column['column'] .'].toLowerCase();
                            if('. ($column['like'] ? '' : '!') .'(val.match(/'. strtolower($column['keyword']) .'/))) {
                                return [[value.bucket,value.key]];
                            }
                        }
                    }
                    return [];
                }
                    ');
                }

            if (!empty($criteria->columns))
                foreach ($criteria->columns as $column) {
                    $column['column'] = \CJavaScript::encode($column['column']);
                    $column['value'] = \CJavaScript::encode($column['value']);
                    $mr->map('
                function(value){
                    if(!value.not_found) {
                        var object = Riak.mapValuesJson(value)[0];
                        if(object.hasOwnProperty('. $column['column'] .')) {
                            if(object['. $column['column'] .'] '. $column['operator'] .' '. $column['value'] .') {
                                return [[value.bucket,value.key]];
                            }
                        }
                    }
                    return [];
                }
                    ');
                }

            /**
             * @todo This function depends on ActiveDocument stored JS functions, disabled until available
             * @todo Waiting on solution from Riak Users regarding running stored JS from bucket
             */
            /*if (!empty($criteria->array))
                foreach ($criteria->array as $column) {
                    $column['column'] = \CJavaScript::encode($column['column']);
                    $column['values'] = \CJavaScript::encode($column['values']);
                    $mr->map('
                function(value){
                    if(!value.not_found) {
                        var object = Riak.mapValuesJson(value)[0];
                        var arr = '. $column['values'] .';
                        if(object.hasOwnProperty('. $column['column'] .')) {
                            if('. ($column['like']?'':'!') .'ActiveDocument.inArray(object['. $column['column'] .'], arr)) {
                                return [[value.bucket,value.key]];
                            }
                        }
                    }
                    return [];
                }
                    ');
                }*/

            if (!empty($criteria->between))
                foreach ($criteria->between as $column) {
                    $column['column'] = \CJavaScript::encode($column['column']);
                    $column['valueStart'] = \CJavaScript::encode($column['valueStart']);
                    $column['valueEnd'] = \CJavaScript::encode($column['valueEnd']);
                    $mr->map('
                function(value){
                    if(!value.not_found) {
                        var object = Riak.mapValuesJson(value)[0];
                        if(object.hasOwnProperty('. $column['column'] .')) {
                            if(object['. $column['column'] .'] >= '. $column['valueStart'] .'
                             && object['. $column['column'] .'] <= '. $column['valueEnd'] .') {
                                return [[value.bucket,value.key]];
                            }
                        }
                    }
                    return [];
                }
                    ');
                }
        }

        return $mr;
    }

    /**
     * @param array $arr
     *
     * @return \ext\activedocument\drivers\riak\Object
     */
    protected function populateObject($arr) {
        return new Object($this->getContainer($arr['bucket']), $arr['key'], \CJSON::decode($arr['values'][0]['data']), true);
    }

}