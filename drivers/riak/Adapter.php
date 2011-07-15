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
 */
class Adapter extends \ext\activedocument\Adapter {

    protected function loadStorageInstance(array $attributes=null) {
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
    public function getMapReduce($reset=false) {
        return $this->_storageInstance->getMapReduce($reset);
    }

    public function count(\ext\activedocument\Criteria $criteria) {
        $mr = $this->getMapReduce(true);
        if (!empty($criteria->container))
            $mr->addBucket($criteria->container);
        
        if (!empty($criteria->inputs))
            foreach ($criteria->inputs as $input)
                if (empty($input['key']))
                    $mr->addBucket($input['container']);
                else
                    $mr->addBucketKeyData($input['container'], $input['key'], $input['data']);
                
        if (!empty($criteria->phases))
            foreach ($criteria->phases as $phase)
                $mr->addPhase($phase['phase'], $phase['function'], $phase['args']);
        
        $mr->map('function(){return [1]}');
        $mr->reduce('Riak.reduceSum');
        $result = $mr->run();
        $result = array_shift($result);
        return $result;
    }

    public function find(\ext\activedocument\Criteria $criteria) {
        $mr = $this->getMapReduce(true);
        if (!empty($criteria->container))
            $mr->addBucket($criteria->container);

        if (!empty($criteria->inputs))
            foreach ($criteria->inputs as $input)
                if (empty($input['key']))
                    $mr->addBucket($input['container']);
                else
                    $mr->addBucketKeyData($input['container'], $input['key'], $input['data']);
                
        if (!empty($criteria->phases))
            foreach ($criteria->phases as $phase)
                $mr->addPhase($phase['phase'], $phase['function'], $phase['args']);
        else
            $mr->map('function(v){return [v];}');
        
        /**
         * @todo IN PROGRESS
         */
        /*if(!empty($criteria->search))
            foreach($criteria->search as $column) {
                $mr->reduce('
                function(value, arg){
                    object = Riak.mapValuesJson(value)[0];
                    if(object["'.$column['column'].'"] '.$column['operator'].' "'.$column['value'].'") {
                        return [value];
                    }
                    return [];
                }
                    ');
            }*/
        
        if(!empty($criteria->columns))
            foreach($criteria->columns as $column) {
                $mr->reduce('
                function(value, arg){
                    object = Riak.mapValuesJson(value)[0];
                    if(object["'.$column['column'].'"] '.$column['operator'].' "'.$column['value'].'") {
                        return [value];
                    }
                    return [];
                }
                    ');
            }
        
        if(!empty($criteria->array))
            ;
        
        if(!empty($criteria->between))
            ;
        
        /**
         * Apply default sorting
         */
        #$mr->reduce('Riak.mapValuesByJson');
        #$mr->reduce('Riak.reduceSort',array('arg'=>'function(a,b){ return a.key-b.key }'));
        //.reduce('Contrib.sort', { by: 'passengers', order: 'desc' })
        if (!empty($criteria->order)) {
            $orderBy = explode(',', $criteria->order);
            foreach($orderBy as $order) {
                preg_match('/(?:(\w+)\.)?(\w+)(?:\s+(ASC|DESC))?/',trim($order),$matches);
                extract(array('field'=>$matches[2],'desc'=>(isset($matches[3]) && strcasecmp($matches[3], 'desc')===0)));
                //$sort = $asc ? 'a.'.$field.'-b.'.$field : 'b.'.$field.'-a.'.$field;
                //return '.$sort.';
                $mr->reduce('Riak.reduceSort',array('arg'=>'
                function(a,b){
                    field = "'.$field.'"
                    str1 = Riak.mapValuesJson('.($desc?'b':'a').')[0];
                    str2 = Riak.mapValuesJson('.($desc?'a':'b').')[0];
                        
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
        #\CVarDumper::dump($mr, 10, true);exit;
        $results = $mr->run();
        $objects = array();
        if (!empty($results))
            foreach ($results as $result)
                $objects[] = $this->populateObject($result);
        return $objects;
    }

    protected function populateObject($arr) {
        return new Object($this->getContainer($arr['bucket']), $arr['key'], \CJSON::decode($arr['values'][0]['data']), true);
    }

    protected function mrOrderBy() {
        '
var sort = function(values, arg) {
    var field = (typeof arg === "undefined" || arg === null) ? undefined : arg.by;
    var reverse = ((typeof arg === "undefined" || arg === null) ? undefined : arg.order) === "desc";
    values.sort(function(a, b) {
        if (reverse) {
            var _ref = [b, a];
            a = _ref[0];
            b = _ref[1];
        }
        if (((typeof a === "undefined" || a === null) ? undefined :
        a[field]) < ((typeof b === "undefined" || b === null) ? undefined :
        b[field])) {
            return -1;
        } else if (((typeof a === "undefined" || a === null) ? undefined :
        a[field]) === ((typeof b === "undefined" || b === null) ? undefined :
        b[field])) {
            return 0;
        } else if (((typeof a === "undefined" || a === null) ? undefined :
        a[field]) > ((typeof b === "undefined" || b === null) ? undefined :
        b[field])) {
            return 1;
        }
    });
};
        ';
    }

}