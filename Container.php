<?php

namespace ext\activedocument;

use \CComponent;

abstract class Container extends CComponent {

    /**
     * @var \ext\activedocument\Adapter
     */
    protected $_adapter;
    /**
     * @var \ext\activedocument\Connection
     */
    protected $_connection;
    protected $_name;
    protected $_containerInstance;
    protected $_propertiesFetched = false;
    protected $_properties;

    abstract protected function loadContainerInstance();

    abstract protected function loadProperties();
    
    abstract public function getKeys();
    
    abstract public function delete();
    
    abstract public function deleteKeys(array $keys);

    abstract public function getObject($key=null);
    
    public function __construct(Adapter $adapter, $name) {
        $this->_adapter = $adapter;
        $this->_connection = $adapter->getConnection();
        $this->_name = $name;
        $this->initContainer();
    }
    
    protected function initContainer() {
        $this->_containerInstance = $this->loadContainerInstance();
        $this->_properties = $this->loadProperties();
    }

    /**
     * @return \ext\activedocument\Connection
     */
    public function getConnection() {
        return $this->_connection;
    }

    /**
     * @return \ext\activedocument\Adapter
     */
    public function getAdapter() {
        return $this->_adapter;
    }
    
    public function getContainerInstance() {
        return $this->_containerInstance;
    }

    public function getName() {
        return $this->_name;
    }

    public function setProperty($key, $value) {
        $this->_properties[$key] = $value;
    }

    public function getProperty($key) {
        if(!array_key_exists($key, $this->_properties))
            return null;
        return $this->_properties[$key];
    }

    public function getProperties() {
        return $this->_properties;
    }

    public function setProperties(array $properties) {
        $this->_properties = $properties;
        foreach ($this->_properties as $k => $v)
            $this->setProperty($k, $v);
    }
    
    public function setConfig(array $config) {
        $this->setProperties($config);
    }
    
    public function count(Criteria $criteria=null) {
        if($criteria===null)
            $criteria = new Criteria;
        $criteria->container = $this->_name;
        return $this->_adapter->count($criteria);
    }
    
    public function find(Criteria $criteria=null) {
        if($criteria===null)
            $criteria = new Criteria;
        $criteria->container = $this->_name;
        return $this->_adapter->find($criteria);
    }
    
    /**
     * @param string $key
     * @param mixed $data
     * @return \ext\activedocument\Object
     */
    public function createObject($key=null, $data=null) {
        return $this->getObject($key, $data, true);
    }

}