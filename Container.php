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

    abstract public function setProperty($key, $value);

    abstract public function getProperty($key);

    abstract protected function loadContainer();

    abstract protected function loadProperties();

    abstract public function getKeys();
    
    abstract public function delete();
    
    abstract public function deleteKeys(array $keys);

    abstract public function getDataObject($key);
    
    abstract public function getAttributes();

    public function __construct(Adapter $adapter, $name) {
        $this->_adapter = $adapter;
        $this->_connection = $adapter->getConnection();
        $this->_name = $name;
        $this->_containerInstance = $this->loadContainer();
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

    public function getProperties() {
        return $this->_properties;
    }

    public function setProperties(array $properties) {
        $this->_properties = $properties;
        foreach ($this->_properties as $k => $v)
            $this->setProperty($k, $v);
    }

}