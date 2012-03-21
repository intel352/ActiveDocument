<?php

namespace ext\activedocument\drivers\riak;

class Object extends \ext\activedocument\Object {

    /**
     * @var \riiak\Object
     */
    protected $_objectInstance;

    /**
     * @param bool $new
     * @return \riiak\Object
     */
    protected function loadObjectInstance($new=true) {
        if ($this->getKey() !== null && !$new)
            return $this->_container->getContainerInstance()->get($this->getKey());
        return $this->_container->getContainerInstance()->newObject($this->getKey());
    }

    /**
     * @return bool
     */
    protected function storeInternal() {
        $this->setObjectData($this->data);
        $this->_objectInstance->store();
        $this->data = $this->getObjectData();
        return $this->_objectInstance->getExists();
    }

    /**
     * @return bool
     */
    protected function deleteInternal() {
        $this->setObjectData($this->data);
        $this->_objectInstance->delete();
        return true;
    }

    /**
     * @return bool
     */
    protected function reloadInternal() {
        $this->setObjectData($this->data);
        $this->_objectInstance->reload();
        $this->data = $this->getObjectData();
        return $this->_objectInstance->getExists();
    }

    /**
     * @return null|string
     */
    public function getKey() {
        if ($this->_objectInstance instanceof \riiak\Object)
            return $this->_objectInstance->key;
        return parent::getKey();
    }

    /**
     * @param string $value
     */
    public function setKey($value) {
        if ($this->_objectInstance instanceof \riiak\Object)
            $this->_objectInstance->key = $value;
        else
            return parent::setKey($value);
    }

    /**
     * @return mixed
     */
    protected function getObjectData() {
        return $this->_objectInstance->getData();
    }

    /**
     * @param mixed $data
     */
    protected function setObjectData($data) {
        $this->_objectInstance->setData($data);
    }

}