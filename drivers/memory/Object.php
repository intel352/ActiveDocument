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
        return new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
    }

    public function store() {
        $this->setObjectData($this->data);
        if(parent::getKey()!==null)
            $this->_container->getContainerInstance()->objects[$this->getKey()] = $this->_objectInstance;
        else
            $this->_container->getContainerInstance()->objects[] = $this->_objectInstance;
        return true;
    }

    public function delete() {
        if($this->getKey()!==null)
            unset($this->_container->getContainerInstance()->objects[$this->getKey()]);
        return true;
    }

    public function reload() {
        $this->_objectInstance = $this->loadObjectInstance(false);
        return true;
    }
    
    public function setKey($key) {
        if($key===$this->getKey())
            return;
        
        if($key!==null)
            $this->_container->getContainerInstance()->objects[$key] = $this->_objectInstance;
        else {
            $this->_container->getContainerInstance()->objects[] = $this->_objectInstance;
            
            $key = array_search($this->_objectInstance, (array) $this->_container->getContainerInstance()->objects, true);
        }
        
        /**
         * Remove old key entry, if existed
         */
        if($this->getKey()!==null)
            unset($this->_container->getContainerInstance()->objects[$this->getKey()]);
        
        return parent::setKey($key);
    }

    protected function getObjectData() {
        return (array) $this->_objectInstance;
    }

    protected function setObjectData($data) {
        $this->_objectInstance->exchangeArray(array_merge((array) $this->_objectInstance,(array) $data));
    }

}