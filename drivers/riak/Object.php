<?php

namespace ext\activedocument\drivers\riak;

class Object extends \ext\activedocument\Object {
    
    /**
     * @var \riiak\Object
     */
    protected $_objectInstance;
    
    protected function loadObjectInstance() {
        return new \riiak\Object($this->_adapter->getStorageInstance(), $this->_container->getContainerInstance(), $this->getKey());
    }
    
    public function store() {
        $this->_objectInstance->store();
        return true;
    }
    
    public function delete() {
        $this->_objectInstance->delete();
        return true;
    }
    
    public function reload() {
        $this->_objectInstance->reload();
        return true;
    }
    
    public function getKey() {
        if($this->_objectInstance instanceof \riiak\Object)
            return $this->_objectInstance->key;
        return parent::getKey();
    }
    
    public function setKey($value) {
        if($this->_objectInstance instanceof \riiak\Object)
            $this->_objectInstance->key = $value;
        return parent::setKey($value);
    }
    
    public function getData() {
        if($this->_objectInstance instanceof \riiak\Object)
            return $this->_objectInstance->getData();
        return parent::getData();
    }
    
    public function setData($value) {
        if($this->_objectInstance instanceof \riiak\Object)
            $this->_objectInstance->setData($value);
        return parent::setData($value);
    }
}