<?php

abstract class ActiveDocument extends CModel {

    public static $conn;
    private static $_models = array();
    private $_new = false;
    private $_attributes = array();
    private $_md;

    public static function model($className=__CLASS__) {
        if (isset(self::$_models[$className]))
            return self::$_models[$className];
        else {
            $model = self::$_models[$className] = new $className(null);
			$model->_md=new ActiveDocumentMetaData($model);
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

    public function __sleep() {
        return array_keys((array) $this);
    }

    public function __get($name) {
        if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
        /* else if (isset($this->getMetaData()->columns[$name]))
          return null;
          else if (isset($this->_related[$name]))
          return $this->_related[$name];
          else if (isset($this->getMetaData()->relations[$name]))
          return $this->getRelated($name); */
        else
            return parent::__get($name);
    }

    public function __set($name, $value) {
        if ($this->setAttribute($name, $value) === false) {
            /* if (isset($this->getMetaData()->relations[$name]))
              $this->_related[$name] = $value;
              else */
            parent::__set($name, $value);
        }
    }

    public function __isset($name) {
        if (isset($this->_attributes[$name]))
            return true;
        /* else if (isset($this->getMetaData()->columns[$name]))
          return false;
          else if (isset($this->_related[$name]))
          return true;
          else if (isset($this->getMetaData()->relations[$name]))
          return $this->getRelated($name) !== null; */
        else
            return parent::__isset($name);
    }

    public function __unset($name) {
        /* if (isset($this->getMetaData()->columns[$name]))
          unset($this->_attributes[$name]);
          else if (isset($this->getMetaData()->relations[$name]))
          unset($this->_related[$name]);
          else */
        parent::__unset($name);
    }

    public function __call($name, $parameters) {
        /* if (isset($this->getMetaData()->relations[$name])) {
          if (empty($parameters))
          return $this->getRelated($name, false);
          else
          return $this->getRelated($name, false, $parameters[0]);
          }

          $scopes = $this->scopes();
          if (isset($scopes[$name])) {
          $this->getDbCriteria()->mergeWith($scopes[$name]);
          return $this;
          } */

        return parent::__call($name, $parameters);
    }

    public function containerName() {
        return get_class($this);
    }

    public function primaryKey() {
        
    }

    public function relations() {
        return array();
    }

    public function scopes() {
        return array();
    }

    public function attributeNames() {
        #return array_keys($this->getMetaData()->columns);
    }

    public function getConnection() {
        if (self::$conn !== null)
            return self::$conn;
        else {
            self::$conn = Yii::app()->getComponent('conn');
            if (self::$conn instanceof ActiveConnection)
                return self::$conn;
            else
                throw new ActiveException(Yii::t('yii', 'Active Document requires a "conn" ActiveConnection application component.'));
        }
    }

    public function getCommandBuilder() {
        return $this->getConnection()->getCommandBuilder();
    }

    public function getMetaData() {
        if ($this->_md !== null)
            return $this->_md;
        else
            return $this->_md = self::model(get_class($this))->_md;
    }

    public function hasAttribute($name) {
        #return isset($this->getMetaData()->columns[$name]);
    }

    public function getAttribute($name) {
        if (property_exists($this, $name))
            return $this->$name;
        else if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
    }

    public function setAttribute($name, $value) {
        if (property_exists($this, $name))
            $this->$name = $value;
        /* else if (isset($this->getMetaData()->columns[$name]))
          $this->_attributes[$name] = $value; */
        else
            return false;
        return true;
    }

    public function getAttributes($names=true) {
        $attributes = $this->_attributes;
        /* foreach ($this->getMetaData()->columns as $name => $column) {
          if (property_exists($this, $name))
          $attributes[$name] = $this->$name;
          else if ($names === true && !isset($attributes[$name]))
          $attributes[$name] = null;
          } */
        if (is_array($names)) {
            $attrs = array();
            foreach ($names as $name) {
                if (property_exists($this, $name))
                    $attrs[$name] = $this->$name;
                else
                    $attrs[$name] = isset($attributes[$name]) ? $attributes[$name] : null;
            }
            return $attrs;
        }
        else
            return $attributes;
    }

    public function save($runValidation=true, $attributes=null) {
        if (!$runValidation || $this->validate($attributes))
            return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
        else
            return false;
    }

    public function getIsNewRecord() {
        return $this->_new;
    }

    public function setIsNewRecord($value) {
        $this->_new = $value;
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

    public function insert($attributes=null) {
        if (!$this->getIsNewRecord())
            throw new ActiveException(Yii::t('yii', 'The active document cannot be inserted because it is not new.'));
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.insert()', 'ext.active-document.' . get_class($this));
            $builder = $this->getCommandBuilder();
            $command = $builder->createInsertCommand($this->containerName(), $this->getAttributes($attributes));
            if ($command->execute()) {
                $primaryKey = $table->primaryKey;
                if ($table->sequenceName !== null) {
                    if (is_string($primaryKey) && $this->$primaryKey === null)
                        $this->$primaryKey = $builder->getLastInsertID($table);
                    else if (is_array($primaryKey)) {
                        foreach ($primaryKey as $pk) {
                            if ($this->$pk === null) {
                                $this->$pk = $builder->getLastInsertID($table);
                                break;
                            }
                        }
                    }
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
            throw new ActiveException(Yii::t('yii', 'The active document cannot be updated because it is new.'));
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.update()', 'ext.active-document.' . get_class($this));
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

    public function saveAttributes($attributes) {
        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.saveAttributes()', 'ext.active-document.' . get_class($this));
            $values = array();
            foreach ($attributes as $name => $value) {
                if (is_integer($name))
                    $values[$value] = $this->$value;
                else
                    $values[$name] = $this->$name = $value;
            }
            if ($this->_pk === null)
                $this->_pk = $this->getPrimaryKey();
            if ($this->updateByPk($this->getOldPrimaryKey(), $values) > 0) {
                $this->_pk = $this->getPrimaryKey();
                return true;
            }
            else
                return false;
        }
        else
            throw new ActiveException(Yii::t('yii', 'The active document cannot be updated because it is new.'));
    }

    public function saveCounters($counters) {
        Yii::trace(get_class($this) . '.saveCounters()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $table = $this->getTableSchema();
        $criteria = $builder->createPkCriteria($table, $this->getOldPrimaryKey());
        $command = $builder->createUpdateCounterCommand($this->getTableSchema(), $counters, $criteria);
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
            Yii::trace(get_class($this) . '.delete()', 'ext.active-document.' . get_class($this));
            if ($this->beforeDelete()) {
                $result = $this->deleteByPk($this->getPrimaryKey()) > 0;
                $this->afterDelete();
                return $result;
            }
            else
                return false;
        }
        else
            throw new ActiveException(Yii::t('yii', 'The active document cannot be deleted because it is new.'));
    }

    public function refresh() {
        Yii::trace(get_class($this) . '.refresh()', 'ext.active-document.' . get_class($this));
        if (!$this->getIsNewRecord() && ($document = $this->findByPk($this->getPrimaryKey())) !== null) {
            $this->_attributes = array();
            $this->_related = array();
            foreach ($this->getMetaData()->columns as $name => $column) {
                if (property_exists($this, $name))
                    $this->$name = $document->$name;
                else
                    $this->_attributes[$name] = $document->$name;
            }
            return true;
        }
        else
            return false;
    }

    public function equals($document) {
        return $this->tableName() === $document->tableName() && $this->getPrimaryKey() === $document->getPrimaryKey();
    }

    public function getPrimaryKey() {
        $table = $this->getMetaData()->tableSchema;
        if (is_string($table->primaryKey))
            return $this->{$table->primaryKey};
        else if (is_array($table->primaryKey)) {
            $values = array();
            foreach ($table->primaryKey as $name)
                $values[$name] = $this->$name;
            return $values;
        }
        else
            return null;
    }

    public function setPrimaryKey($value) {
        $this->_pk = $this->getPrimaryKey();
        $table = $this->getMetaData()->tableSchema;
        if (is_string($table->primaryKey))
            $this->{$table->primaryKey} = $value;
        else if (is_array($table->primaryKey)) {
            foreach ($table->primaryKey as $name)
                $this->$name = $value[$name];
        }
    }

    public function getOldPrimaryKey() {
        return $this->_pk;
    }

    public function setOldPrimaryKey($value) {
        $this->_pk = $value;
    }

    protected function query($criteria, $all=false) {
        $this->beforeFind();
        $this->applyScopes($criteria);
        if (empty($criteria->with)) {
            if (!$all)
                $criteria->limit = 1;
            $command = $this->getCommandBuilder()->createFindCommand($this->getTableSchema(), $criteria, $this->getTableAlias());
            return $all ? $this->populateRecords($command->queryAll(), true, $criteria->index) : $this->populateRecord($command->queryRow());
        }
        else {
            $finder = new CActiveFinder($this, $criteria->with);
            return $finder->query($criteria, $all);
        }
    }

    public function find($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.find()', 'ext.active-document.' . get_class($this));
        $criteria = $this->getCommandBuilder()->createCriteria($condition, $params);
        return $this->query($criteria);
    }

    public function findAll($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findAll()', 'ext.active-document.' . get_class($this));
        $criteria = $this->getCommandBuilder()->createCriteria($condition, $params);
        return $this->query($criteria, true);
    }

    public function findByPk($pk, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findByPk()', 'ext.active-document.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $criteria = $this->getCommandBuilder()->createPkCriteria($this->getTableSchema(), $pk, $condition, $params, $prefix);
        return $this->query($criteria);
    }

    public function findAllByPk($pk, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findAllByPk()', 'ext.active-document.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $criteria = $this->getCommandBuilder()->createPkCriteria($this->getTableSchema(), $pk, $condition, $params, $prefix);
        return $this->query($criteria, true);
    }

    public function findByAttributes($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findByAttributes()', 'ext.active-document.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $criteria = $this->getCommandBuilder()->createColumnCriteria($this->getTableSchema(), $attributes, $condition, $params, $prefix);
        return $this->query($criteria);
    }

    public function findAllByAttributes($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.findAllByAttributes()', 'ext.active-document.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $criteria = $this->getCommandBuilder()->createColumnCriteria($this->getTableSchema(), $attributes, $condition, $params, $prefix);
        return $this->query($criteria, true);
    }

    public function count($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.count()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $this->applyScopes($criteria);

        if (empty($criteria->with))
            return $builder->createCountCommand($this->getTableSchema(), $criteria, $this->getTableAlias())->queryScalar();
        else {
            $finder = new CActiveFinder($this, $criteria->with);
            return $finder->count($criteria, $this->getTableAlias());
        }
    }

    public function countByAttributes($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.countByAttributes()', 'ext.active-document.' . get_class($this));
        $prefix = $this->getTableAlias(true) . '.';
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createColumnCriteria($this->getTableSchema(), $attributes, $condition, $params, $prefix);
        $this->applyScopes($criteria);

        if (empty($criteria->with))
            return $builder->createCountCommand($this->getTableSchema(), $criteria, $this->getTableAlias())->queryScalar();
        else {
            $finder = new CActiveFinder($this, $criteria->with);
            return $finder->count($criteria, $this->getTableAlias());
        }
    }

    public function exists($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.exists()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $table = $this->getTableSchema();
        $criteria->select = '1';
        $criteria->limit = 1;
        $this->applyScopes($criteria);
        return $builder->createFindCommand($table, $criteria, $this->getTableAlias())->queryRow() !== false;
    }

    public function updateByPk($pk, $attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.updateByPk()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $table = $this->getTableSchema();
        $criteria = $builder->createPkCriteria($table, $pk, $condition, $params);
        $command = $builder->createUpdateCommand($table, $attributes, $criteria);
        return $command->execute();
    }

    public function updateAll($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.updateAll()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $command = $builder->createUpdateCommand($this->getTableSchema(), $attributes, $criteria);
        return $command->execute();
    }

    public function updateCounters($counters, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.updateCounters()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $command = $builder->createUpdateCounterCommand($this->getTableSchema(), $counters, $criteria);
        return $command->execute();
    }

    public function deleteByPk($pk, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.deleteByPk()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createPkCriteria($this->getTableSchema(), $pk, $condition, $params);
        $command = $builder->createDeleteCommand($this->getTableSchema(), $criteria);
        return $command->execute();
    }

    public function deleteAll($condition='', $params=array()) {
        Yii::trace(get_class($this) . '.deleteAll()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $criteria = $builder->createCriteria($condition, $params);
        $command = $builder->createDeleteCommand($this->getTableSchema(), $criteria);
        return $command->execute();
    }

    public function deleteAllByAttributes($attributes, $condition='', $params=array()) {
        Yii::trace(get_class($this) . '.deleteAllByAttributes()', 'ext.active-document.' . get_class($this));
        $builder = $this->getCommandBuilder();
        $table = $this->getTableSchema();
        $criteria = $builder->createColumnCriteria($table, $attributes, $condition, $params);
        $command = $builder->createDeleteCommand($table, $criteria);
        return $command->execute();
    }

    public function populateRecord($attributes, $callAfterFind=true) {
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

    public function populateRecords($data, $callAfterFind=true, $index=null) {
        $documents = array();
        foreach ($data as $attributes) {
            if (($document = $this->populateRecord($attributes, $callAfterFind)) !== null) {
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