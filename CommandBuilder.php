<?php

namespace ext\activedocument;
use \CComponent;

abstract class CommandBuilder extends CComponent {
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

    abstract public function createFindCommand($container, $criteria);

    abstract public function createCountCommand($container, $criteria);

    abstract public function createDeleteCommand($container, $criteria);

    abstract public function createInsertCommand($container, $data);

    abstract public function createUpdateCommand($container, $data, $criteria);

    abstract public function createUpdateCounterCommand($container, $counters, $criteria);

    protected function ensureContainer(&$container) {
        if (is_string($container) && ($container = $this->_adapter->getContainer($containerName = $container)) === null)
            throw new Exception(Yii::t('yii', 'Container "{container}" does not exist.', array('{container}' => $containerName)));
    }

}