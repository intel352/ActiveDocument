<?php

namespace ext\activedocument;

use \Yii,
\CComponent;

/**
 * @property mixed|null $key
 */
abstract class Object extends \CComponent {

    /**
     * @var \ext\activedocument\Adapter
     */
    protected $_adapter;

    /**
     * @var \ext\activedocument\Connection
     */
    protected $_connection;

    /**
     * @var \ext\activedocument\Container
     */
    protected $_container;

    /**
     * @var mixed
     */
    private $_key;

    /**
     * @var mixed
     */
    public $data;
    protected $_objectInstance;

    abstract protected function loadObjectInstance($new=true);

    /**
     * @abstract
     * @return bool
     */
    abstract protected function storeInternal();

    /**
     * @abstract
     * @return bool
     */
    abstract protected function deleteInternal();

    /**
     * @abstract
     * @return bool
     */
    abstract protected function reloadInternal();

    /**
     * @abstract
     * @return mixed
     */
    abstract protected function getObjectData();

    /**
     * @abstract
     * @param mixed $data
     */
    abstract protected function setObjectData($data);

    /**
     * @param \ext\activedocument\Container $container
     * @param string|null $key optional
     * @param mixed|null $data optional
     * @param bool $new optional
     */
    public function __construct(Container $container, $key=null, $data=null, $new=true) {
        $this->_container = $container;
        $this->_adapter = $container->getAdapter();
        $this->_connection = $container->getConnection();
        $this->_key = $key;
        $this->_objectInstance = $this->loadObjectInstance($new);
        /**
         * Sync data
         */
        $this->syncData($data);
    }

    /**
     * @param mixed $data
     */
    protected function syncData($data) {
        if ($data !== null)
            $this->setObjectData($data);
        $this->data = $this->getObjectData();
    }

    /**
     * @return \ext\activedocument\Container
     */
    public function getContainer() {
        return $this->_container;
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

    public function getObjectInstance() {
        return $this->_objectInstance;
    }

    /**
     * @return null|mixed
     */
    public function getKey() {
        return $this->_key;
    }

    /**
     * @param mixed $value
     */
    public function setKey($value) {
        $this->_key = $value;
    }

    /**
     * @return bool
     */
    public function store() {
        if ($this->getConnection()->enableProfiling) {
            $profileToken = 'ext.activedocument.execute.storeObject(Storing object with key: '.\CVarDumper::dumpAsString($this->getKey()).')';
            Yii::beginProfile($profileToken, 'ext.activedocument.execute.storeObject');
        }

        $result = $this->storeInternal();

        if ($this->getConnection()->enableProfiling)
            Yii::endProfile($profileToken, 'ext.activedocument.execute.storeObject');

        return $result;
    }

    /**
     * @return bool
     */
    public function delete() {
        if ($this->getConnection()->enableProfiling) {
            $profileToken = 'ext.activedocument.execute.deleteObject(Deleting object with key: '.\CVarDumper::dumpAsString($this->getKey()).')';
            Yii::beginProfile($profileToken, 'ext.activedocument.execute.deleteObject');
        }

        $result = $this->deleteInternal();

        if ($this->getConnection()->enableProfiling)
            Yii::endProfile($profileToken, 'ext.activedocument.execute.deleteObject');

        return $result;
    }

    /**
     * @return bool
     */
    public function reload() {
        if ($this->getConnection()->enableProfiling) {
            $profileToken = 'ext.activedocument.query.reloadObject(Reloading object with key: '.\CVarDumper::dumpAsString($this->getKey()).')';
            Yii::beginProfile($profileToken, 'ext.activedocument.query.reloadObject');
        }

        $result = $this->reloadInternal();

        if ($this->getConnection()->enableProfiling)
            Yii::endProfile($profileToken, 'ext.activedocument.query.reloadObject');

        return $result;
    }

}