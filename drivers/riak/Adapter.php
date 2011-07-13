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
        if(!empty($attributes))
            foreach($attributes as $key=>$value)
                $storageInstance->$key=$value;
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
        if(!empty($criteria->container))
            $mr->addBucket($criteria->container);
        if(!empty($criteria->inputs))
            foreach($criteria->inputs as $input)
                if(empty($input[1]))
                    $mr->addBucket($input[0]);
                else
                    $mr->addBucketKeyData($input[0], $input[1], $input[2]);
        if(!empty($criteria->phases))
            foreach($criteria->phases as $phase)
                $mr->addPhase($phase[0], $phase[1], $phase[2]);
        $mr->map('function(){return [1]}');
        $mr->reduce('Riak.reduceSum');
        $result = $mr->run();
        $result = array_shift($result);
        return $result;
    }
    
    public function find(\ext\activedocument\Criteria $criteria) {
        $mr = $this->getMapReduce(true);
        if(!empty($criteria->container))
            $mr->addBucket($criteria->container);
        if(!empty($criteria->inputs))
            foreach($criteria->inputs as $input)
                if(empty($input[1]))
                    $mr->addBucket($input[0]);
                else
                    $mr->addBucketKeyData($input[0], $input[1], $input[2]);
        if(!empty($criteria->phases))
            foreach($criteria->phases as $phase)
                $mr->addPhase($phase[0], $phase[1], $phase[2]);
        else
            $mr->map('function(v){return [v];}');
        if($criteria->limit>0)
            $mr->reduce('Riak.reduceSlice', array('arg'=>array($criteria->offset>0?$criteria->offset:0, $criteria->limit)));
        $results = $mr->run();
        $objects = array();
        if(!empty($results))
            foreach($results as $result)
                $objects[] = $this->populateObject($result);
        return $objects;
    }
    
    protected function populateObject($arr) {
        return new Object($this->getContainer($arr['bucket']), $arr['key'], \CJSON::decode($arr['values'][0]['data']), true);
    }

}