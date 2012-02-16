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
    /**
     * @var string
     */
    protected $_name;
    protected $_containerInstance;
    /**
     * @var bool
     */
    protected $_propertiesFetched = false;
    /**
     * @var array
     */
    protected $_properties;

    abstract protected function loadContainerInstance();

    /**
     * @abstract
     * @return array
     */
    abstract protected function loadProperties();

    /**
     * @abstract
     * @return array
     */
    abstract public function getKeys();

    /**
     * @abstract
     * @return bool
     */
    abstract public function delete();

    /**
     * @abstract
     * @param array $keys
     * @return bool
     */
    abstract public function deleteKeys(array $keys);

    /**
     * @abstract
     * @param string $key Optional Key to initialize object. Default null
     * @param mixed $data Optional Data to initialize object. Default null
     * @param bool $new Optional Whether object is new. Default false
     * @return \ext\activedocument\Object
     */
    abstract public function getObject($key = null, $data = null, $new = false);

    /**
     * @param \ext\activedocument\Adapter $adapter
     * @param string $name
     */
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

    /**
     * @return string
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setProperty($key, $value) {
        $this->_properties[$key] = $value;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getProperty($key) {
        if (!array_key_exists($key, $this->_properties))
            return null;
        return $this->_properties[$key];
    }

    /**
     * @return array
     */
    public function getProperties() {
        return $this->_properties;
    }

    /**
     * @param array $properties
     */
    public function setProperties(array $properties) {
        $this->_properties = $properties;
        foreach ($this->_properties as $k => $v)
            $this->setProperty($k, $v);
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config) {
        $this->setProperties($config);
    }

    /**
     * @param \ext\activedocument\Criteria|null $criteria optional
     * @return int
     */
    public function count(Criteria $criteria=null) {
        if ($criteria === null)
            $criteria = new Criteria;
        $criteria->container = $this->_name;
        return $this->_adapter->count($criteria);
    }

    /**
     * @param \ext\activedocument\Criteria|null $criteria optional
     * @return array[]\ext\activedocument\Object
     */
    public function find(Criteria $criteria=null) {
        if ($criteria === null)
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