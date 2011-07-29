<?php

namespace ext\activedocument\drivers\memory;

/**
 * Object for Memory driver
 * 
 * @version $Version$
 * @author $Author$
 */
class Object extends \ext\activedocument\Object {

    protected function loadObjectInstance($new=true) {
        if ($this->getKey() !== null && !$new)
            return $this->_container->getContainerInstance()->objects[$this->getKey()];
        return $this->_container->getContainerInstance()->objects[$this->getKey()] = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
    }

    public function store() {
        $this->setObjectData($this->data);
        $this->data = (array) $this->_container->getContainerInstance()->objects[$this->getKey()] = $this->_objectInstance;
        return true;
    }

    public function delete() {
        unset($this->_container->getContainerInstance()->objects[$this->getKey()]);
        return true;
    }

    public function reload() {
        $this->setObjectData($this->data);
        $this->data = $this->getObjectData();
        return array_key_exists($this->getKey(), $this->_container->getContainerInstance()->objects);
    }
    
    public function getKey() {
        $key = parent::getKey();
        if($key===null) {
            $key = array_search($this->_objectInstance, (array) $this->_container->getContainerInstance()->objects, true);
            parent::setKey($key);
        }
        return $key;
    }

    public function setKey($value) {
        if($value===null || $value===$this->getKey())
            return;
        
        if($value!==null)
            $this->_container->getContainerInstance()->objects[$value] = $this->_container->getContainerInstance()->objects[$this->getKey()];
        else
            $this->_container->getContainerInstance()->objects[] = $this->_container->getContainerInstance()->objects[$this->getKey()];
        
        /**
         * Remove old key entry
         */
        if($this->getKey()!==null)
            unset($this->_container->getContainerInstance()->objects[$this->getKey()]);
        
        /**
         * Determine generated key (if $value was null)
         */
        if($value===null)
            $value = array_search($this->_objectInstance, (array) $this->_container->getContainerInstance()->objects, true);
        return parent::setKey($value);
    }

    protected function getObjectData() {
        return (array) $this->_objectInstance;
    }

    protected function setObjectData($data) {
        $this->_objectInstance->exchangeArray(array_merge((array) $this->_objectInstance,(array) $data));
    }

}