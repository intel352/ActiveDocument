<?php

namespace ext\activedocument\drivers\mongo;

/**
 * Object for Mongo driver
 */
class Object extends \ext\activedocument\Object {

    protected function loadObjectInstance($new = true) {
        $data = null;
        if ($this->getKey() !== null && !$new) {
            \Yii::trace('Mongo FindByPk query: ' . \CVarDumper::dumpAsString($this->getKey()), 'ext.activedocument.drivers.mongo.Object');
            $data = $this->_container->getContainerInstance()->findOne(array('_id' => $this->getKey()));
        }
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
            /**
             * Ensure _id is not null or empty string. Empty string is a valid key in mongo
             */
            if(isset($this->_objectInstance->_id) && ($this->_objectInstance->_id===null && $this->_objectInstance->_id!==''))
                unset($this->_objectInstance->_id);

            /**
             * When we aren't specifying a pk, we should insert, which will update _objectInstance with new pk
             */
            if(!isset($this->_objectInstance->_id))
                $this->_container->getContainerInstance()->insert($this->_objectInstance, array('safe'=>true));
            else
                $this->_container->getContainerInstance()->save($this->_objectInstance, array('safe'=>true));
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
            $this->_container->getContainerInstance()->remove(array('_id'=> $this->getKey()), array('safe'=>true));
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
     * @return \MongoId|null|string
     */
    public function getKey() {
        if($this->_objectInstance instanceof \ArrayObject && isset($this->_objectInstance->_id))
            $key = $this->_objectInstance->_id;
        else
            $key = parent::getKey();
        $key = self::properId($key);
        \Yii::trace('Mongo getKey(): ' . \CVarDumper::dumpAsString($key), 'ext.activedocument.drivers.mongo.Object');

        return $key;
    }

    /**
     * @param string|\MongoId $value
     */
    public function setKey($value) {
        $value = self::properId($value);
        \Yii::trace('Mongo setKey(): ' . \CVarDumper::dumpAsString($value), 'ext.activedocument.drivers.mongo.Object');
        if($this->_objectInstance instanceof \ArrayObject) {
            $this->_objectInstance->_id = $value;
        }
        return parent::setKey($value);
    }

    /**
     * @param mixed $id
     * @return \MongoId|mixed
     */
    public static function properId($id) {
        if ($id === null)
            return $id;
        if (is_array($id) && isset($id['$id']))
            return new \MongoId($id['$id']);
        elseif (is_string($id) && ($mId = new \MongoId($id)) && $id === (string) $mId)
            return $mId;
        return $id;
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