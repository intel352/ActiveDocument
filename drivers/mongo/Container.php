<?php

namespace ext\activedocument\drivers\mongo;

/**
 * Container for Mongo driver
 */
class Container extends \ext\activedocument\Container {

    protected function loadContainerInstance() {
        return $this->_adapter->getStorageInstance()->selectCollection($this->_name);
    }

    protected function loadProperties() {
        return array(
            'w'=>$this->_containerInstance->w,
            'wtimeout'=>$this->_containerInstance->wtimeout,
        );
    }

    public function setProperty($key, $value) {
        $this->_properties[$key] = $this->_containerInstance->$key = $value;
    }

    public function delete() {
        /**
         * @todo Log response, throw exception if error occurred
         */
        $this->_containerInstance->drop();
    }

    /**
     * @return array
     */
    public function getKeys() {
        return $this->_adapter->getStorageInstance()->command(array(
            'distinct'=>$this->_containerInstance->getName(),
            'key'=>'_id',
        ));
    }

    public function deleteKeys(array $keys) {
        try {
            $this->_containerInstance->remove(array('_id'=>array('$in'=>$keys)));
        }catch(\MongoException $e) {
            /**
             * @todo Throw custom exception
             */
            return false;
        }
        return true;
    }

    /**
     * @param string $key
     * @param mixed $data
     * @param bool $new
     * @return \ext\activedocument\drivers\mongo\Object
     */
    public function getObject($key = null, $data = null, $new = false) {
        return new Object($this, $key, $data, $new);
    }

}