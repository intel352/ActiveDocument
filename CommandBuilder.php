<?php

namespace ext\activedocument;
use \CComponent;

class CommandBuilder extends CComponent {
    const PARAM_PREFIX=':ap';

    /**
     * @var \ext\activedocument\Adapter
     */
    protected $_adapter;
    /**
     * @var \ext\activedocument\Connection
     */
    protected $_connection;

    public function __construct(Adapter $adapter) {
        $this->_adapter = $adapter;
        $this->_connection = $adapter->getConnection();
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

    public function createFindCommand($container, $criteria) {
    }

    public function createCountCommand($container, $criteria) {
    }

    public function createDeleteCommand($container, $criteria) {
    }

    public function createInsertCommand($container, $data) {
    }

    public function createUpdateCommand($container, $data, $criteria) {
    }

    public function createUpdateCounterCommand($container, $counters, $criteria) {
    }

    protected function ensureContainer(&$container) {
        if (is_string($container) && ($container = $this->_adapter->getContainer($containerName = $container)) === null)
            throw new Exception(Yii::t('yii', 'Container "{container}" does not exist.', array('{container}' => $containerName)));
    }

}