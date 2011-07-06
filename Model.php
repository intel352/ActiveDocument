<?php

namespace ext\activedocument;
use \Yii, \CModel, \CEvent, \CModelEvent;

abstract class Model extends CModel {

    /**
     * @var \ext\activedocument\Connection
     */
    public static $conn;
    private static $_models = array();
    private $_md;
    protected $_container;

    public static function model($className=__CLASS__) {
        if (isset(self::$_models[$className]))
            return self::$_models[$className];
        else {
            $model = self::$_models[$className] = new $className(null);
			$model->_md=new MetaData($model);
            $model->attachBehaviors($model->behaviors());
            return $model;
        }
    }

    public function __construct($scenario='insert') {
        if ($scenario === null)
            return;

        $this->setScenario($scenario);
        $this->setIsNewRecord(true);
        $this->_attributes = $this->getMetaData()->attributeDefaults;

        $this->init();

        $this->attachBehaviors($this->behaviors());
        $this->afterConstruct();
    }

    public function init() {
        
    }

    /* public function cache($duration, $dependency=null, $queryCount=1) {
      $this->getDbConnection()->cache($duration, $dependency, $queryCount);
      return $this;
      } */

    public function primaryKey() {
        
    }

    public function relations() {
        return array();
    }

    public function scopes() {
        return array();
    }

    public function attributeNames() {
        return array_keys($this->getMetaData()->attributes);
    }

    /**
     * @return \ext\activedocument\Connection
     */
    public function getConnection() {
        if (self::$conn !== null)
            return self::$conn;
        else {
            self::$conn = Yii::app()->getComponent('conn');
            if (self::$conn instanceof Connection)
                return self::$conn;
            else
                throw new Exception(Yii::t('yii', 'Active Document requires a "conn" Connection application component.'));
        }
    }

    public function getCommandBuilder() {
        return $this->getConnection()->getCommandBuilder();
    }
    
    public function getAdapter() {
        return $this->getConnection()->getAdapter();
    }

    public function getContainerName() {
        return get_class($this);
    }
    
    /**
     * @return \ext\activedocument\Container
     */
    public function getContainer() {
        if($this->_container===null)
            $this->_container = $this->getAdapter()->getContainer($this->getContainerName());
        return $this->_container;
    }

    /**
     * @return \ext\activedocument\MetaData
     */
    public function getMetaData() {
        if ($this->_md === null)
            $this->_md = self::model(get_class($this))->_md;
        return $this->_md;
    }
    
    public function save($runValidation=true, $attributes=null) {
        if (!$runValidation || $this->validate($attributes))
            return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
        else
            return false;
    }

    public function onBeforeSave($event) {
        $this->raiseEvent('onBeforeSave', $event);
    }

    public function onAfterSave($event) {
        $this->raiseEvent('onAfterSave', $event);
    }

    public function onBeforeDelete($event) {
        $this->raiseEvent('onBeforeDelete', $event);
    }

    public function onAfterDelete($event) {
        $this->raiseEvent('onAfterDelete', $event);
    }

    public function onBeforeFind($event) {
        $this->raiseEvent('onBeforeFind', $event);
    }

    public function onAfterFind($event) {
        $this->raiseEvent('onAfterFind', $event);
    }

    protected function beforeSave() {
        if ($this->hasEventHandler('onBeforeSave')) {
            $event = new CModelEvent($this);
            $this->onBeforeSave($event);
            return $event->isValid;
        }
        else
            return true;
    }

    protected function afterSave() {
        if ($this->hasEventHandler('onAfterSave'))
            $this->onAfterSave(new CEvent($this));
    }

    protected function beforeDelete() {
        if ($this->hasEventHandler('onBeforeDelete')) {
            $event = new CModelEvent($this);
            $this->onBeforeDelete($event);
            return $event->isValid;
        }
        else
            return true;
    }

    protected function afterDelete() {
        if ($this->hasEventHandler('onAfterDelete'))
            $this->onAfterDelete(new CEvent($this));
    }

    protected function beforeFind() {
        if ($this->hasEventHandler('onBeforeFind')) {
            $event = new CModelEvent($this);
            // for backward compatibility
            $event->criteria = func_num_args() > 0 ? func_get_arg(0) : null;
            $this->onBeforeFind($event);
        }
    }

    protected function afterFind() {
        if ($this->hasEventHandler('onAfterFind'))
            $this->onAfterFind(new CEvent($this));
    }

    public function beforeFindInternal() {
        $this->beforeFind();
    }

    public function afterFindInternal() {
        $this->afterFind();
    }

    public function insert(Document $document, array $attributes=null) {
        if (!$document->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be inserted because it is not new.'));
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.insert()', 'ext.activedocument.' . get_class($this));
            $builder = $this->getCommandBuilder();
            $command = $builder->createInsertCommand($this->getContainerName(), $document->getAttributes($attributes));
            if ($command->execute()) {
                $primaryKey = $table->primaryKey;
                if ($table->sequenceName !== null) {
                    if (is_string($primaryKey) && $this->$primaryKey === null)
                        $this->$primaryKey = $builder->getLastInsertID($table);
                }
                $this->_pk = $this->getPrimaryKey();
                $this->afterSave();
                $this->setIsNewRecord(false);
                $this->setScenario('update');
                return true;
            }
        }
        return false;
    }

    public function update($attributes=null) {
        if ($this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be updated because it is new.'));
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.update()', 'ext.activedocument.' . get_class($this));
            if ($this->_pk === null)
                $this->_pk = $this->getPrimaryKey();
            $this->updateByPk($this->getOldPrimaryKey(), $this->getAttributes($attributes));
            $this->_pk = $this->getPrimaryKey();
            $this->afterSave();
            return true;
        }
        else
            return false;
    }

    public function saveAttributes(Document $document, array $attributes) {
        if (!$document->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.saveAttributes()', 'ext.activedocument.' . get_class($this));
            $values = array();
            foreach ($attributes as $name => $value) {
                if (is_integer($name))
                    $values[$value] = $document->$value;
                else
                    $values[$name] = $document->$name = $value;
            }
            if ($document->getPrimaryKey() === null)
                $this->_pk = $this->getPrimaryKey();
            if ($this->updateByPk($this->getOldPrimaryKey(), $values) > 0) {
                $this->_pk = $this->getPrimaryKey();
                return true;
            }
            else
                return false;
        }
        else
            throw new Exception(Yii::t('yii', 'The document cannot be updated because it is new.'));
    }

    public function saveCounters($counters) {
        Yii::trace(get_class($this) . '.saveCounters()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $metaData = $this->getMetaData();
        $criteria = $builder->createPkCriteria($metaData, $this->getOldPrimaryKey());
        $command = $builder->createUpdateCounterCommand($this->getMetaData(), $counters, $criteria);
        if ($command->execute()) {
            foreach ($counters as $name => $value)
                $this->$name = $this->$name + $value;
            return true;
        }
        else
            return false;
    }

    public function delete() {
        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'ext.activedocument.' . get_class($this));
            if ($this->beforeDelete()) {
                $result = $this->deleteByPk($this->getPrimaryKey()) > 0;
                $this->afterDelete();
                return $result;
            }
            else
                return false;
        }
        else
            throw new Exception(Yii::t('yii', 'The document cannot be deleted because it is new.'));
    }

    protected function query($criteria, $all=false) {
        $this->beforeFind();
        $this->applyScopes($criteria);
        if (empty($criteria->with)) {
            if (!$all)
                $criteria->limit = 1;
            $command = $this->getCommandBuilder()->createFindCommand($this->getMetaData(), $criteria, $this->getTableAlias());
            return $all ? $this->populateDocuments($command->queryAll(), true, $criteria->index) : $this->populateDocument($command->queryRow());
        }
        else {
            $finder = new CActiveFinder($this, $criteria->with);
            return $finder->query($criteria, $all);
        }
    }

    public function find($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.find()', 'ext.activedocument.' . get_class($this));
        $criteria = $this->getCommandBuilder()->createCriteria($condition, $params);
        return $this->query($criteria);
    }

    public function findAll($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findAll()', 'ext.activedocument.' . get_class($this));
        $criteria = $this->getCommandBuilder()->createCriteria($condition, $params);
        return $this->query($criteria, true);
    }

    public function findByPk($pk, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findByPk()', 'ext.activedocument.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $criteria = $this->getCommandBuilder()->createPkCriteria($this->getMetaData(), $pk, $condition, $params, $prefix);
        return $this->query($criteria);
    }

    public function findAllByPk($pk, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findAllByPk()', 'ext.activedocument.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $criteria = $this->getCommandBuilder()->createPkCriteria($this->getMetaData(), $pk, $condition, $params, $prefix);
        return $this->query($criteria, true);
    }

    public function findByAttributes($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findByAttributes()', 'ext.activedocument.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $criteria = $this->getCommandBuilder()->createColumnCriteria($this->getMetaData(), $attributes, $condition, $params, $prefix);
        return $this->query($criteria);
    }

    public function findAllByAttributes($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findAllByAttributes()', 'ext.activedocument.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $criteria = $this->getCommandBuilder()->createColumnCriteria($this->getMetaData(), $attributes, $condition, $params, $prefix);
        return $this->query($criteria, true);
    }

    public function count($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.count()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $this->applyScopes($criteria);

        if (empty($criteria->with))
            return $builder->createCountCommand($this->getMetaData(), $criteria, $this->getTableAlias())->queryScalar();
        else {
            $finder = new CActiveFinder($this, $criteria->with);
            return $finder->count($criteria, $this->getTableAlias());
        }
    }

    public function countByAttributes($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.countByAttributes()', 'ext.activedocument.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createColumnCriteria($this->getMetaData(), $attributes, $condition, $params, $prefix);
        $this->applyScopes($criteria);

        if (empty($criteria->with))
            return $builder->createCountCommand($this->getMetaData(), $criteria, $this->getTableAlias())->queryScalar();
        else {
            $finder = new CActiveFinder($this, $criteria->with);
            return $finder->count($criteria, $this->getTableAlias());
        }
    }

    public function exists($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.exists()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $metaData = $this->getMetaData();
        $criteria->select = '1';
        $criteria->limit = 1;
        $this->applyScopes($criteria);
        return $builder->createFindCommand($metaData, $criteria, $this->getTableAlias())->queryRow() !== false;
    }

    public function updateByPk($pk, $attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.updateByPk()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $metaData = $this->getMetaData();
        $criteria = $builder->createPkCriteria($metaData, $pk, $condition, $params);
        $command = $builder->createUpdateCommand($metaData, $attributes, $criteria);
        return $command->execute();
    }

    public function updateAll($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.updateAll()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $command = $builder->createUpdateCommand($this->getMetaData(), $attributes, $criteria);
        return $command->execute();
    }

    public function updateCounters($counters, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.updateCounters()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $command = $builder->createUpdateCounterCommand($this->getMetaData(), $counters, $criteria);
        return $command->execute();
    }

    public function deleteByPk($pk, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.deleteByPk()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createPkCriteria($this->getMetaData(), $pk, $condition, $params);
        $command = $builder->createDeleteCommand($this->getMetaData(), $criteria);
        return $command->execute();
    }

    public function deleteAll($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.deleteAll()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $command = $builder->createDeleteCommand($this->getMetaData(), $criteria);
        return $command->execute();
    }

    public function deleteAllByAttributes($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.deleteAllByAttributes()', 'ext.activedocument.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $metaData = $this->getMetaData();
        $criteria = $builder->createColumnCriteria($metaData, $attributes, $condition, $params);
        $command = $builder->createDeleteCommand($metaData, $criteria);
        return $command->execute();
    }

    public function populateDocument($attributes, $callAfterFind=true) {
        if ($attributes !== false) {
            $document = $this->instantiate($attributes);
            $document->setScenario('update');
            $document->init();
            $md = $document->getMetaData();
            foreach ($attributes as $name => $value) {
                if (property_exists($document, $name))
                    $document->$name = $value;
                else if (isset($md->columns[$name]))
                    $document->_attributes[$name] = $value;
            }
            $document->_pk = $document->getPrimaryKey();
            $document->attachBehaviors($document->behaviors());
            if ($callAfterFind)
                $document->afterFind();
            return $document;
        }
        else
            return null;
    }

    public function populateDocuments($data, $callAfterFind=true, $index=null) {
        $documents = array();
        foreach ($data as $attributes) {
            if (($document = $this->populateDocument($attributes, $callAfterFind)) !== null) {
                if ($index === null)
                    $documents[] = $document;
                else
                    $documents[$document->$index] = $document;
            }
        }
        return $documents;
    }

    protected function instantiate($attributes) {
        $class = get_class($this);
        $model = new $class(null);
        return $model;
    }

    public function offsetExists($offset) {
        return $this->__isset($offset);
    }

}