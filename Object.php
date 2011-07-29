<?php

namespace ext\activedocument;

use \CComponent;

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
     * @var string
     */
    private $_key;
    /**
     * @var mixed
     */
    public $data;
    protected $_objectInstance;

    abstract protected function loadObjectInstance($new=true);

    abstract public function store();

    abstract public function delete();

    abstract public function reload();
    
    abstract protected function getObjectData();
    
    abstract protected function setObjectData($data);

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
    
    protected function syncData($data) {
        if($data!==null)
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

    public function getKey() {
        return $this->_key;
    }

    public function setKey($value) {
        $this->_key = $value;
    }

}