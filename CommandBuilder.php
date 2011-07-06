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

    public function getLastInsertID() {
        /*$this->ensureContainer($container);
        if ($container->sequenceName !== null)
            return $this->_connection->getLastInsertID($container->sequenceName);
        else
            return null;*/
    }

    public function createFindCommand($container, $criteria, $alias='t') {
    }

    public function createCountCommand($container, $criteria, $alias='t') {
    }

    public function createDeleteCommand($container, $criteria) {
    }

    public function createInsertCommand($container, $data) {
    }

    public function createUpdateCommand($container, $data, $criteria) {
    }

    public function createUpdateCounterCommand($container, $counters, $criteria) {
    }

    public function createSqlCommand($sql, $params=array()) {
    }

    public function applyJoin($sql, $join) {
    }

    public function applyCondition($sql, $condition) {
    }

    public function applyOrder($sql, $orderBy) {
    }

    public function applyLimit($sql, $limit, $offset) {
    }

    public function applyGroup($sql, $group) {
    }

    public function applyHaving($sql, $having) {
    }

    public function bindValues($command, $values) {
    }

    public function createCriteria($condition='', $params=array()) {
    }

    public function createPkCriteria($container, $pk, $condition='', $params=array(), $prefix=null) {
    }

    public function createPkCondition($container, $values, $prefix=null) {
    }

    public function createColumnCriteria($container, $columns, $condition='', $params=array(), $prefix=null) {
    }

    public function createSearchCondition($container, $columns, $keywords, $prefix=null, $caseSensitive=true) {
    }

    public function createInCondition($container, $columnName, $values, $prefix=null) {
    }

    protected function createCompositeInCondition($container, $values, $prefix) {
    }

    protected function ensureContainer(&$container) {
        if (is_string($container) && ($container = $this->_adapter->getContainer($containerName = $container)) === null)
            throw new Exception(Yii::t('yii', 'Container "{container}" does not exist.', array('{container}' => $containerName)));
    }

}