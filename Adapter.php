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
    /**
     * @var array
     */
    protected $_containers = array();
    /**
     * @var array
     */
    protected $_cacheExclude = array();

    abstract protected function loadStorageInstance(array $attributes = null);

    /**
     * @abstract
     * @param string $name
     * @return \ext\activedocument\Container
     */
    abstract protected function loadContainer($name);

    /**
     * @abstract
     * @param \ext\activedocument\Criteria $criteria
     * @return int
     */
    abstract protected function countInternal(Criteria $criteria);

    /**
     * @abstract
     * @param \ext\activedocument\Criteria $criteria
     * @return \ext\activedocument\Object[]
     */
    abstract protected function findInternal(Criteria $criteria);

    /**
     * @param \ext\activedocument\Connection $conn
     * @param array|null $attributes optional
     */
    public function __construct(Connection $conn, array $attributes = null) {
        $this->_connection = $conn;
        foreach ($conn->schemaCachingExclude as $name)
            $this->_cacheExclude[$name] = true;
        $this->_storageInstance = $this->loadStorageInstance($attributes);
    }

    public function __get($name) {
        if (property_exists($this->_storageInstance, $name) || ($this->_storageInstance instanceof CComponent && $this->_storageInstance->canGetProperty($name)))
            return $this->_storageInstance->$name;
        return parent::__get($name);
    }

    public function __set($name, $value) {
        if (property_exists($this->_storageInstance, $name) || ($this->_storageInstance instanceof CComponent && $this->_storageInstance->canSetProperty($name)))
            $this->_storageInstance->$name = $value;
        else
            return parent::__set($name, $value);
    }

    public function __isset($name) {
        if (property_exists($this->_storageInstance, $name) || ($this->_storageInstance instanceof CComponent && $this->_storageInstance->canGetProperty($name)))
            return $this->_storageInstance->$name !== null;
        return parent::__isset($name);
    }

    public function __unset($name) {
        if (property_exists($this->_storageInstance, $name) || ($this->_storageInstance instanceof CComponent && $this->_storageInstance->canSetProperty($name)))
            return $this->_storageInstance->$name = null;
        return parent::__unset($name);
    }

    public function getStorageInstance() {
        return $this->_storageInstance;
    }

    /**
     * @return \ext\activedocument\Connection Data storage connection. The connection is active.
     */
    public function getConnection() {
        return $this->_connection;
    }

    /**
     * @return array
     */
    public function getConfig() {
        return $this->_connection->attributes;
    }

    /**
     * @param string $name
     * @param array $config
     * @return \ext\activedocument\Container
     */
    public function getContainer($name, array $config = array()) {
        if (isset($this->_containers[$name]))
            return $this->_containers[$name];
        else {
            if (strpos($name, '{{') !== false)
                $realName = preg_replace('/\{\{(.*?)\}\}/', $this->_connection->containerPrefix . '$1', $name);
            else
                $realName = $name;

            // temporarily disable query caching
            /* if($this->_connection->queryCachingDuration>0)
              {
              $qcDuration=$this->_connection->queryCachingDuration;
              $this->_connection->queryCachingDuration=0;
              } */

            if (!isset($this->_cacheExclude[$name]) && ($duration = $this->_connection->schemaCachingDuration) > 0 && $this->_connection->schemaCacheID !== false && ($cache = Yii::app()->getComponent($this->_connection->schemaCacheID)) !== null) {
                $key = 'activedocument.storageschema.' . $this->_connection->driver . '.' . $name;
                if (($container = $cache->get($key)) === false) {
                    $container = $this->loadContainer($realName);
                    if ($container !== null)
                        $cache->set($key, $container, $duration);
                }
            }
            else
                $container = $this->loadContainer($realName);

            if (!empty($config))
                $container->setConfig($config);

            /* if(isset($qcDuration))  // re-enable query caching
              $this->_connection->queryCachingDuration=$qcDuration; */

            return $this->_containers[$name] = $container;
        }
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     * @return int
     */
    public function count(Criteria $criteria) {
        if ($this->getConnection()->enableProfiling) {
            $profileToken = 'ext.activedocument.query.count(' . \CVarDumper::dumpAsString(array_filter($criteria->toArray())) . ')';
            Yii::beginProfile($profileToken, 'ext.activedocument.query.count');
        }

        $result = $this->countInternal($criteria);

        if ($this->getConnection()->enableProfiling)
            Yii::endProfile($profileToken, 'ext.activedocument.query.count');

        return $result;
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     * @return \ext\activedocument\Object[]
     */
    public function find(Criteria $criteria) {
        if ($this->getConnection()->enableProfiling) {
            $profileToken = 'ext.activedocument.query.find(' . \CVarDumper::dumpAsString(array_filter($criteria->toArray())) . ')';
            Yii::beginProfile($profileToken, 'ext.activedocument.query.find');
        }

        $result = $this->findInternal($criteria);

        if ($this->getConnection()->enableProfiling)
            Yii::endProfile($profileToken, 'ext.activedocument.query.find');

        return $result;
    }

}