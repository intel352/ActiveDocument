<?php

namespace ext\activedocument\drivers\riak;

class Object extends \ext\activedocument\Object {

    /**
     * @var \riiak\Object
     */
    protected $_objectInstance;

    protected function loadObjectInstance($new=true) {
        if($this->getKey()!==null && !$new)
            return $this->_container->getContainerInstance()->get($this->getKey());
        return $this->_container->getContainerInstance()->newObject($this->getKey());
    }

    public function store() {
        $this->setObjectData($this->data);
        $this->_objectInstance->store();
        $this->data = $this->getObjectData();
        return $this->_objectInstance->getExists();
    }

    public function delete() {
        $this->setObjectData($this->data);
        $this->_objectInstance->delete();
        return true;
    }

    public function reload() {
        $this->setObjectData($this->data);
        $this->_objectInstance->reload();
        $this->data = $this->getObjectData();
        return $this->_objectInstance->getExists();
    }

    public function getKey() {
        if ($this->_objectInstance instanceof \riiak\Object)
            return $this->_objectInstance->key;
        return parent::getKey();
    }

    public function setKey($value) {
        if ($this->_objectInstance instanceof \riiak\Object)
            $this->_objectInstance->key = $value;
        else
            return parent::setKey($value);
    }

    protected function getObjectData() {
        return $this->_objectInstance->getData();
    }
    
    protected function setObjectData($data) {
        $this->_objectInstance->setData($data);
    }
}
