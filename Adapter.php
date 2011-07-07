<?php

namespace ext\activedocument;

use \Yii,
    \CComponent;

abstract class Adapter extends CComponent {

    /**
     * @var \ext\activedocument\Connection
     */
    protected $_connection;
    protected $_storageInstance;
    protected $_containers = array();
    protected $_cacheExclude = array();
    protected $_errorCode;
    protected $_errorInfo;
    protected $_runningTransaction = false;

    abstract protected function loadStorageInstance(array $attributes=null);

    abstract protected function loadContainer($name);

    public function __construct(Connection $conn, array $attributes=null) {
        $this->_connection = $conn;
        foreach ($conn->schemaCachingExclude as $name)
            $this->_cacheExclude[$name] = true;
        $this->_storageInstance = $this->loadStorageInstance($attributes);
    }

    public function __get($name) {
        if (property_exists($this->_storageInstance, $name) || $this->_storageInstance->canGetProperty($name))
            return $this->_storageInstance->$name;
        return parent::__get($name);
    }

    public function __set($name, $value) {
        if (property_exists($this->_storageInstance, $name) || $this->_storageInstance->canSetProperty($name))
            $this->_storageInstance->$name = $value;
        else
            return parent::__set($name, $value);
    }

    public function __isset($name) {
        if (property_exists($this->_storageInstance, $name) || $this->_storageInstance->canGetProperty($name))
            return $this->_storageInstance->$name !== null;
        return parent::__isset($name);
    }

    public function __unset($name) {
        if (property_exists($this->_storageInstance, $name) || $this->_storageInstance->canSetProperty($name))
            return $this->_storageInstance->$name = null;
        return parent::__unset($name);
    }

    public function getStorageInstance() {
        return $this->_storageInstance;
    }

    /**
     * @return Connection storage connection. The connection is active.
     */
    public function getConnection() {
        return $this->_connection;
    }

    public function getConfig() {
        return $this->_connection->attributes;
    }

    /**
     * @param string $name
     * @return \ext\activedocument\Container
     */
    public function getContainer($name, array $config=array()) {
        if (isset($this->_containers[$name]))
            return $this->_containers[$name];
        else {
            if ($this->_connection->containerPrefix !== null && strpos($name, '{{') !== false)
                $realName = preg_replace('/\{\{(.*?)\}\}/', $this->_connection->containerPrefix . '$1', $name);
            else
                $realName=$name;

            // temporarily disable query caching
            /* if($this->_connection->queryCachingDuration>0)
              {
              $qcDuration=$this->_connection->queryCachingDuration;
              $this->_connection->queryCachingDuration=0;
              } */

            if (!isset($this->_cacheExclude[$name]) && ($duration = $this->_connection->schemaCachingDuration) > 0 && $this->_connection->schemaCacheID !== false && ($cache = Yii::app()->getComponent($this->_connection->schemaCacheID)) !== null) {
                $key = 'activedocument.storageschema.' . $this->_connection->driver . '.' . $this->_connection->containerPrefix . '.' . $name;
                if (($container = $cache->get($key)) === false) {
                    $container = $this->loadContainer($realName);
                    if ($container !== null)
                        $cache->set($key, $container, $duration);
                }
            }
            else
                $container = $this->loadContainer($realName);
            
            if(!empty($config))
                $container->setConfig($config);

            /* if(isset($qcDuration))  // re-enable query caching
              $this->_connection->queryCachingDuration=$qcDuration; */

            return $this->_containers[$name] = $container;
        }
    }

    public function beginTransaction() {
        $this->_runningTransaction = true;
    }

    public function commit() {
        $this->_runningTransaction = false;
    }

    public function getErrorCode() {
        return $this->_errorCode;
    }

    public function getErrorInfo() {
        return $this->_errorInfo;
    }

    public function exec() {
        
    }

    public function getInTransaction() {
        return $this->_runningTransaction;
    }

    public function prepare() {
        ;
    }

    public function query() {
        ;
    }

    public function quote() {
        ;
    }

    public function rollBack() {
        ;
    }

}