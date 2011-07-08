<?php

namespace ext\activedocument;

use \Yii,
    \CModel,
    \CEvent,
    \CModelEvent;

abstract class Document extends CModel {

    /**
     * @var \ext\activedocument\Connection
     */
    public static $conn;
    private static $_models = array();
    /**
     * @var \ext\activedocument\MetaData
     */
    private $_md;
    /**
     * @var \ext\activedocument\Container
     */
    protected $_container;
    /**
     * @var \ext\activedocument\Object
     */
    protected $_object;
    protected $_new = false;
    protected $_attributes = array();
    protected $_pk;

    /**
     * @return \ext\activedocument\Document
     */
    public static function model() {
        $className=get_called_class();
        if (isset(self::$_models[$className]))
            return self::$_models[$className];
        else {
            $document = self::$_models[$className] = new $className(null);
            $document->_md = new MetaData($document);
            $document->attachBehaviors($document->behaviors());
            return $document;
        }
    }

    public function __construct($scenario='insert') {
        if ($scenario === null)
            return;

        $this->setScenario($scenario);
        $this->setIsNewRecord(true);
        $this->newObject();

        $this->init();

        $this->attachBehaviors($this->behaviors());
        $this->afterConstruct();
    }

    public function init() {
        
    }

    protected function newObject() {
        $this->setObject($this->loadObject());
    }

    protected function loadObject($key=null) {
        return $this->getContainer()->getObject($key, null, $this->getIsNewRecord());
    }

    /**
     * @return \ext\activedocument\Object
     */
    public function getObject() {
        return $this->_object;
    }

    public function setObject(Object $object) {
        $this->_object = $object;
        $this->ensurePk();
        if ($this->getIsNewRecord())
            $this->_object->data = $this->getMetaData()->attributeDefaults;
        $this->setAttributes($this->_object->data, false);
    }

    public function getIsNewRecord() {
        return $this->_new;
    }

    public function setIsNewRecord($value) {
        $this->_new = $value;
    }

    public function __sleep() {
        $this->_md = null;
        return array_keys((array) $this);
    }

    public function __get($name) {
        if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
        else if (isset($this->getMetaData()->attributes[$name]))
            return null;
        /* else if (isset($this->_related[$name]))
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
        else if (isset($this->getMetaData()->attributes[$name]))
            return false;
        /* else if (isset($this->_related[$name]))
          return true;
          else if (isset($this->getMetaData()->relations[$name]))
          return $this->getRelated($name) !== null; */
        else
            return parent::__isset($name);
    }

    public function __unset($name) {
        if (isset($this->getMetaData()->attributes[$name]))
            unset($this->_attributes[$name]);
        /*  else if (isset($this->getMetaData()->relations[$name]))
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

    public function hasAttribute($name) {
        return isset($this->getMetaData()->attributes[$name]);
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
        else if (isset($this->getMetaData()->attributes[$name]))
            $this->_attributes[$name] = $value;
        else
            return false;
        return true;
    }

    public function getAttributes($names=true) {
        $attributes = $this->_attributes;
        foreach ($this->getMetaData()->attributes as $name => $attr) {
            if (property_exists($this, $name))
                $attributes[$name] = $this->$name;
            else if ($names === true && !isset($attributes[$name]))
                $attributes[$name] = null;
        }
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

    public function refresh() {
        Yii::trace(get_class($this) . '.refresh()', 'ext.activedocument.' . get_class($this));
        if (!$this->getIsNewRecord() && $this->getObject()->reload()) {
            $this->_related = array();
            $object = $this->getObject();
            foreach ($this->getMetaData()->attributes as $name => $attr) {
                if (property_exists($this, $name))
                    $this->$name = $object->data[$name];
            }
            return true;
        }
        else
            return false;
    }

    public function equals(Document $document) {
        return $this->getContainerName() === $document->getContainerName() && $this->getPrimaryKey() === $document->getPrimaryKey();
    }

    public function primaryKey() {
        return '_pk';
    }

    public function getPrimaryKey() {
        $pk = $this->primaryKey();
        if (is_string($pk))
            return $this->{$pk};
        else {
            $return = array();
            foreach ($pk as $pkField)
                $return[] = $this->{$pkField};

            return $return;
        }
    }
    
    protected function ensurePk() {
        if($this->_pk===null)
            if(!empty($this->primaryKey))
                $this->_pk = $this->getPrimaryKey();
            elseif(!empty($this->_object->key))
                $this->_pk = $this->_object->getKey();
        if(is_array($this->_pk))
            $this->_pk = implode('_', $this->_pk);
    }

    /* public function cache($duration, $dependency=null, $queryCount=1) {
      $this->getDbConnection()->cache($duration, $dependency, $queryCount);
      return $this;
      } */

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
        if ($this->_container === null)
            $this->_container = $this->getAdapter()->getContainer($this->getContainerName(), $this->containerConfig());
        return $this->_container;
    }

    public function containerConfig() {
        return array();
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

    protected function store(array $attributes=null) {
        $attributes = $this->getAttributes($attributes);
        foreach ($attributes as $name => $value) {
            $this->_object->data[$name] = $value;
        }
        $this->_object->setKey($this->_pk);
        return $this->_object->store();
    }

    public function insert(array $attributes=null) {
        if (!$this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be inserted because it is not new.'));
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.insert()', 'ext.activedocument.' . get_class($this));
            $this->ensurePk();
            if ($this->store($attributes)) {
                $this->_pk = $this->_object->getKey();
                $this->afterSave();
                $this->setIsNewRecord(false);
                $this->setScenario('update');
                return true;
            }
        }
        return false;
    }

    public function update(array $attributes=null) {
        if ($this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be updated because it is new.'));
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.update()', 'ext.activedocument.' . get_class($this));
            $this->ensurePk();
            if ($this->store($attributes)) {
                $this->_pk = $this->_object->getKey();
                $this->afterSave();
                return true;
            }
        }
        return false;
    }

    public function saveAttributes(array $attributes) {
        if ($this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be updated because it is new.'));
        Yii::trace(get_class($this) . '.saveAttributes()', 'ext.activedocument.' . get_class($this));
        $this->setAttributes($attributes, false);
        $this->ensurePk();
        if ($this->store(array_keys($attributes))) {
            $this->_pk = $this->_object->getKey();
            return true;
        }
        return false;
    }

    public function delete() {
        if ($this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be deleted because it is new.'));
        Yii::trace(get_class($this) . '.delete()', 'ext.activedocument.' . get_class($this));
        if ($this->beforeDelete()) {
            $this->ensurePk();
            $result = $this->_object->delete();
            $this->afterDelete();
            return $result;
        }
        else
            return false;
    }

    /**
     * @param string $key
     * @return \ext\activedocument\Document
     */
    public function findByPk($key) {
        Yii::trace(get_class($this) . '.findByPk()', 'ext.activedocument.' . get_class($this));
        $this->beforeFind();
        return $this->populateDocument($this->loadObject($key));
    }

    public function findAll(array $keys=null) {
        Yii::trace(get_class($this) . '.findAll()', 'ext.activedocument.' . get_class($this));
        $this->beforeFind();
        if ($keys === null)
            $keys = $this->_container->getKeys();
        if (empty($keys))
            return array();

        $objects = array();
        foreach ($keys as $key)
            $objects[] = $this->loadObject($key);

        return $this->populateDocuments($objects);
    }

    /**
     * @param Object $object
     * @param bool $callAfterFind
     * @return \ext\activedocument\Document
     */
    public function populateDocument(Object $object, $callAfterFind=true) {
        $document = $this->instantiate($object);
        $document->setScenario('update');
        $document->setObject($object);
        $document->init();
        $document->attachBehaviors($document->behaviors());
        if ($callAfterFind)
            $document->afterFind();
        return $document;
    }

    public function populateDocuments(array $objects, $callAfterFind=true, $index=null) {
        $documents = array();
        foreach ($objects as $object) {
            if (($document = $this->populateDocument($object, $callAfterFind)) !== null) {
                if ($index === null)
                    $documents[] = $document;
                else
                    $documents[$document->$index] = $document;
            }
        }
        return $documents;
    }

    /**
     * @param \ext\activedocument\Object $object
     * @return \ext\activedocument\Document
     */
    protected function instantiate(Object $object) {
        $class = get_class($this);
        $document = new $class(null);
        return $document;
    }

    public function offsetExists($offset) {
        return $this->__isset($offset);
    }

}