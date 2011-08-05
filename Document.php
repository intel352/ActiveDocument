<?php

namespace ext\activedocument;

use \Yii,
    \CModel,
    \CEvent,
    \CModelEvent;

Yii::import('ext.activedocument.Relation', true);

/**
 * Document
 * 
 * @todo Relations are almost in place, need mechanism for determining
 * how keys will be managed
 *
 * @version $Version$
 * @author $Author$
 */
abstract class Document extends CModel {
    const BELONGS_TO='\ext\activedocument\BelongsToRelation';
    const HAS_ONE='\ext\activedocument\HasOneRelation';
    const HAS_MANY='\ext\activedocument\HasManyRelation';
    const MANY_MANY='\ext\activedocument\ManyManyRelation';
    const STAT='\ext\activedocument\StatRelation';
    const NESTED_ONE=1;
    const NESTED_MANY=2;
    const NESTED_INDEX=3;

    /**
     * Override with component connection name, if not 'conn'
     *
     * @var string
     */
    public static $connName = 'conn';

    /**
     * Array of connections
     *
     * @var array \ext\activedocument\Connection
     */
    public static $connections = array();
    private static $_models = array();
    protected $_related = array();

    /**
     * @var \ext\activedocument\MetaData
     */
    private $_md;

    /**
     * @var \ext\activedocument\Criteria
     */
    private $_c;

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
     * @var \ext\activedocument\Document
     */
    protected $_owner;

    /**
     * @return \ext\activedocument\Document
     */
    public static function model($className=null) {
        if ($className === null)
            $className = get_called_class();
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

    public function getOwner() {
        return $this->_owner;
    }

    public function setOwner(Document $owner) {
        $this->_owner = $owner;
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

    public function getCriteria($createIfNull=true) {
        if ($this->_c === null) {
            if (($c = $this->defaultScope()) !== array() || $createIfNull)
                $this->_c = new Criteria($c);
        }
        return $this->_c;
    }

    public function setCriteria($criteria) {
        $this->_c = $criteria;
    }

    public function defaultScope() {
        return array();
    }

    public function resetScope() {
        $this->_c = new Criteria();
        return $this;
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
        else if (isset($this->_related[$name]))
            return $this->_related[$name];
        else if (isset($this->getMetaData()->relations[$name]))
            return $this->getRelated($name);
        else
            return parent::__get($name);
    }

    public function __set($name, $value) {
        if ($this->setAttribute($name, $value) === false) {
            if (isset($this->getMetaData()->relations[$name]))
                $this->_related[$name] = $value;
            else
                parent::__set($name, $value);
        }
    }

    public function __isset($name) {
        if (isset($this->_attributes[$name]))
            return true;
        else if (isset($this->getMetaData()->attributes[$name]))
            return false;
        else if (isset($this->_related[$name]))
            return true;
        else if (isset($this->getMetaData()->relations[$name]))
            return $this->getRelated($name) !== null;
        else
            return parent::__isset($name);
    }

    public function __unset($name) {
        if (isset($this->getMetaData()->attributes[$name]))
            unset($this->_attributes[$name]);
        else if (isset($this->getMetaData()->relations[$name]))
            unset($this->_related[$name]);
        else
            parent::__unset($name);
    }

    public function __call($name, $parameters) {
        if (isset($this->getMetaData()->relations[$name])) {
            if (empty($parameters))
                return $this->getRelated($name, false);
            else
                return $this->getRelated($name, false, $parameters[0]);
        }

        /* $scopes = $this->scopes();
          if (isset($scopes[$name])) {
          $this->getCriteria()->mergeWith($scopes[$name]);
          return $this;
          } */

        return parent::__call($name, $parameters);
    }

    /**
     * Returns the related record(s).
     * This method will return the related record(s) of the current record.
     * If the relation is HAS_ONE or BELONGS_TO, it will return a single object
     * or null if the object does not exist.
     * If the relation is HAS_MANY or MANY_MANY, it will return an array of objects
     * or an empty array.
     * @param string $name the relation name (see {@link relations})
     * @param boolean $refresh whether to reload the related objects from database. Defaults to false.
     * @param array $params additional parameters that customize the query conditions as specified in the relation declaration.
     * @return mixed the related object(s).
     * @throws Exception if the relation is not specified in {@link relations}.
     */
    public function getRelated($name, $refresh=false, array $params=array()) {
        if (!$refresh && $params === array() && (isset($this->_related[$name]) || array_key_exists($name, $this->_related)))
            return $this->_related[$name];

        $md = $this->getMetaData();
        if (!isset($md->relations[$name]))
            throw new Exception(Yii::t('yii', '{class} does not have relation "{name}".', array('{class}' => get_class($this), '{name}' => $name)));

        Yii::trace('lazy loading ' . get_class($this) . '.' . $name, 'ext.activedocument.' . get_class($this));
        $relation = $md->relations[$name];
        if ($this->getIsNewRecord() && !$refresh && ($relation instanceof HasOneRelation || $relation instanceof HasManyRelation))
            return $relation instanceof HasOneRelation ? null : array();

        if ($params !== array()) { // dynamic query
            $exists = isset($this->_related[$name]) || array_key_exists($name, $this->_related);
            if ($exists)
                $save = $this->_related[$name];
        }
        unset($this->_related[$name]);

        if ($relation instanceof HasManyRelation)
            $this->_related[$name] = Document::model($relation->className)->findAll($params);
        else
            $this->_related[$name] = Document::model($relation->className)->find($params);

        if (!isset($this->_related[$name])) {
            if ($relation instanceof HasManyRelation)
                $this->_related[$name] = array();
            else if ($relation instanceof StatRelation)
                $this->_related[$name] = $relation->defaultValue;
            else
                $this->_related[$name] = null;
        }

        if ($params !== array()) {
            $results = $this->_related[$name];
            if ($exists)
                $this->_related[$name] = $save;
            else
                unset($this->_related[$name]);
            return $results;
        }
        else
            return $this->_related[$name];
    }

    /**
     * Returns a value indicating whether the named related object(s) has been loaded.
     * @param string $name the relation name
     * @return boolean a value indicating whether the named related object(s) has been loaded.
     */
    public function hasRelated($name) {
        return isset($this->_related[$name]) || array_key_exists($name, $this->_related);
    }

    /**
     * Do not call this method. This method is used internally to populate
     * related objects. This method adds a related object to this record.
     * @param string $name attribute name
     * @param mixed $record the related record
     * @param mixed $index the index value in the related object collection.
     * If true, it means using zero-based integer index.
     * If false, it means a HAS_ONE or BELONGS_TO object and no index is needed.
     */
    public function addRelatedRecord($name, $record, $index) {
        if ($index !== false) {
            if (!isset($this->_related[$name]))
                $this->_related[$name] = array();
            if ($record instanceof Document) {
                if ($index === true)
                    $this->_related[$name][] = $record;
                else
                    $this->_related[$name][$index] = $record;
            }
        }
        else if (!isset($this->_related[$name]))
            $this->_related[$name] = $record;
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
            $isNull = true;
            $return = array();
            foreach ($pk as $pkField) {
                $isNull = & is_null($this->{$pkField}) || $this->{$pkField} === '';
                $return[$pkField] = is_null($this->{$pkField}) ? '' : $this->{$pkField};
            }

            /**
             * If all pk values are empty/null, return null
             */
            if ($isNull)
                return null;
            return $return;
        }
    }

    protected function ensurePk() {
        if ($this->_pk === null)
            if ($this->primaryKey() !== '_pk' && $this->getPrimaryKey() !== null)
                $this->_pk = $this->jsonEncode($this->getPrimaryKey());
            elseif ($this->_object->getKey() !== null)
                $this->_pk = $this->_object->getKey();
    }

    protected function jsonEncode($var) {
        /**
         * Return var if already valid JSON
         */
        if (is_null($var) || is_bool($var) || (is_numeric($var) && !is_string($var)) || (is_string($var) && \CJSON::decode($var) !== null))
            return $var;
        return \CJSON::encode($var);
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

    public function rules() {
        return array_merge(parent::rules(), array(
                    array(implode(', ', $this->attributeNames()), 'safe', 'on' => 'search'),
                ));
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return \ext\activedocument\DataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search() {
        $criteria = new Criteria;

        foreach ($this->getMetaData()->getAttributes() as $name => $attribute) {
            if ($attribute->type === 'string')
                $criteria->compare($name, $this->$name, true);
            else
                $criteria->compare($name, $this->$name);
        }

        return new DataProvider(get_class($this), array(
                    'criteria' => $criteria,
                ));
    }

    /**
     * @return \ext\activedocument\Connection
     */
    public function getConnection() {
        if (empty(static::$connName))
            throw new Exception(Yii::t('yii', 'Active Document requires that Document::$connName not be empty.'));

        if (array_key_exists(static::$connName, self::$connections) && self::$connections[static::$connName] !== null)
            return self::$connections[static::$connName];
        else {
            self::$connections[static::$connName] = Yii::app()->getComponent(static::$connName);
            if (self::$connections[static::$connName] instanceof Connection)
                return self::$connections[static::$connName];
            else
                throw new Exception(Yii::t('yii', 'Active Document requires a "' . static::$connName . '" Connection application component.'));
        }
    }

    public function getAdapter() {
        return $this->getConnection()->getAdapter();
    }

    public function containerName() {
        return get_class($this);
    }

    public function getContainerName() {
        return $this->containerName();
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

    public function count($condition=null, array $params=array()) {
        Yii::trace(get_class($this) . '.count()', 'ext.activedocument.' . get_class($this));
        $criteria = $this->buildCriteria($condition, $params);
        $this->applyScopes($criteria);
        return $this->_container->count($criteria);
    }

    public function find($condition=null, array $params=array()) {
        Yii::trace(get_class($this) . '.find()', 'ext.activedocument.' . get_class($this));
        return $this->query($this->buildCriteria($condition, $params));
    }

    /**
     * @param string $key
     * @return \ext\activedocument\Document
     */
    public function findByPk($key, $condition=null, array $params=array()) {
        Yii::trace(get_class($this) . '.findByPk()', 'ext.activedocument.' . get_class($this));
        return $this->query($this->buildCriteria($condition, $params), false, array($key));
    }

    public function findAll($condition=null, array $params=array()) {
        Yii::trace(get_class($this) . '.findAll()', 'ext.activedocument.' . get_class($this));
        return $this->query($this->buildCriteria($condition, $params), true);
    }

    public function findAllByPk(array $keys, $condition=null, array $params=array()) {
        Yii::trace(get_class($this) . '.findAllByPk()', 'ext.activedocument.' . get_class($this));
        return $this->query($this->buildCriteria($condition, $params), true, $keys);
    }

    protected function query($criteria, $all=false, $keys=array()) {
        $this->beforeFind();
        $this->applyScopes($criteria);

        if (!empty($keys))
            $keys = array_map(array($this, 'jsonEncode'), $keys);

        $objects = array();
        $emptyCriteria = new Criteria;
        if ($criteria == $emptyCriteria && !empty($keys))
            foreach ($keys as $key)
                $objects[] = $this->loadObject($key);
        else {
            if (!$all)
                $criteria->limit = 1;
            if (!empty($keys))
                foreach ($keys as $key)
                    $criteria->addInput($this->containerName(), $key);
            $objects = $this->_container->find($criteria);
        }

        if (empty($objects))
            return $all ? array() : null;

        return $all ? $this->populateDocuments($objects) : $this->populateDocument(array_shift($objects));
    }

    protected function buildCriteria($condition, $params=array()) {
        if (is_array($condition))
            $criteria = new Criteria($condition);
        else if ($condition instanceof Criteria)
            $criteria = clone $condition;
        else
            $criteria = new Criteria;

        if (!empty($params))
            $criteria->mergeWith(array('params' => $params));

        return $criteria;
    }

    /**
     * Applies the query scopes to the given criteria.
     * This method merges {@link criteria} with the given criteria parameter.
     * It then resets {@link criteria} to be null.
     * @param Criteria $criteria the query criteria. This parameter may be modified by merging {@link criteria}.
     */
    public function applyScopes(&$criteria) {
        /* if (!empty($criteria->scopes)) {
          $scs = $this->scopes();
          $c = $this->getCriteria();
          foreach ((array) $criteria->scopes as $k => $v) {
          if (is_integer($k)) {
          if (is_string($v)) {
          if (isset($scs[$v])) {
          $c->mergeWith($scs[$v], true);
          continue;
          }
          $scope = $v;
          $params = array();
          } else if (is_array($v)) {
          $scope = key($v);
          $params = current($v);
          }
          } else if (is_string($k)) {
          $scope = $k;
          $params = $v;
          }

          call_user_func_array(array($this, $scope), (array) $params);
          }
          } */

        if (isset($c) || ($c = $this->getCriteria(false)) !== null) {
            $c->mergeWith($criteria);
            $criteria = $c;
            $this->_c = null;
        }
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