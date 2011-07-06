<?php

namespace ext\activedocument\drivers\riak;

class Container extends \ext\activedocument\Container {
    
    /**
     * @var \riiak\Bucket
     */
    protected $_containerInstance;
    
    /**
     * @return \riiak\Bucket
     */
    protected function loadContainer() {
        return $this->_connection->bucket($this->_name);
    }

    public function setProperty($key, $value) {
        $this->_containerInstance->setProperty($key, $value);
        $this->_properties[$key] = $value;
    }

    public function getProperty($key) {
        if(!key_exists($key, $this->_properties))
            $this->_properties[$key] = $this->_containerInstance->getProperty($key);
        return $this->_properties[$key];
    }

    protected function loadProperties() {
        return $this->_containerInstance->getProperties();
    }
    
    /**
     * Overriding default setProperties method, as Riiak supports massively saving properties
     *
     * @param array $properties 
     */
    public function setProperties(array $properties) {
        $this->_properties = $properties;
        $this->_containerInstance->setProperties($this->_properties);
    }
    
    public function delete() {
        return $this->deleteKeys($this->getKeys());
    }

    /**
     * @return array 
     */
    public function getKeys() {
        return $this->_containerInstance->getKeys();
    }
    
    public function deleteKeys(array $keys) {
        foreach($keys as $key)
            $this->getDataObject($key)->delete();
        return true;
    }

    /**
     * @param string $key
     * @return \ext\activedocument\drivers\riak\Object
     */
    public function getDataObject($key) {
        return new Object($this, $key);
    }
    
    public function getAttributes() {
        return array();
    }

}