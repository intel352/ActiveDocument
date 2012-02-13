<?php

namespace ext\activedocument\drivers\mongo;

/**
 * Object for Mongo driver
 */
class Object extends \ext\activedocument\Object {

    protected function loadObjectInstance($new = true) {
        $data = null;
        if ($this->getKey() !== null && !$new)
            $data = $this->_container->getContainerInstance()->findOne(array('_id' => $this->getKey()));
        if ($data == null)
            $data = array();
        return new \ArrayObject($data, \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @return bool
     */
    protected function storeInternal() {
        $this->setObjectData($this->data);
        try {
            $this->_container->getContainerInstance()->save($this->getObjectData());
            $this->data = $this->getObjectData();
        }catch(\MongoException $e) {
            /**
             * @todo Throw custom exception
             */
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function deleteInternal() {
        $this->setObjectData($this->data);
        try {
            $this->_container->getContainerInstance()->remove(array('_id'=> $this->getKey()));
        }catch(\MongoException $e) {
            /**
             * @todo Throw custom exception
             */
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function reloadInternal() {
        $this->setObjectData($this->data);
        $this->_objectInstance = $this->loadObjectInstance(false);
        $this->data = $this->getObjectData();
        return true;
    }

    /**
     * @return null|string
     */
    public function getKey() {
        if($this->_objectInstance instanceof \ArrayObject && isset($this->_objectInstance->_id))
            return (string) $this->_objectInstance->_id;
        return parent::getKey();
    }

    /**
     * @param string $value
     */
    public function setKey($value) {
        if($this->_objectInstance instanceof \ArrayObject) {
            $this->_objectInstance->_id = $value;
        }else
            return parent::setKey($value);
    }

    /**
     * @return mixed
     */
    protected function getObjectData() {
        return (array) $this->_objectInstance;
    }

    /**
     * @param mixed $data
     */
    protected function setObjectData($data) {
        $this->_objectInstance->exchangeArray(array_merge((array)$this->_objectInstance, (array)$data));
    }
}