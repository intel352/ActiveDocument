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
    
}