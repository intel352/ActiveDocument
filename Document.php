<?php

namespace ext\activedocument;

use \Yii,
\CModel,
\CEvent;

Yii::import('ext.activedocument.Relation', true);

/**
 * Document
 *
 * @todo    Relations are almost in place, need mechanism for determining how keys will be managed
 *
 * @version $Version: 1.0.dev.56 $
 * @author  $Author: intel352 $
 *
 * @property \ext\activedocument\Document $owner
 * @property \ext\activedocument\Object $object
 * @property bool $isNewRecord
 * @property \ext\activedocument\Criteria $criteria
 * @property mixed $primaryKey
 * @property-read string $encodedPk
 * @property-read \ext\activedocument\Connection $connection
 * @property-read \ext\activedocument\Adapter $adapter
 * @property-read string $containerName
 * @property-read \ext\activedocument\Container $container
 * @property-read \ext\activedocument\MetaData $metaData
 */
abstract class Document extends CModel {

    const BELONGS_TO = '\ext\activedocument\BelongsToRelation';
    const HAS_ONE    = '\ext\activedocument\HasOneRelation';
    const HAS_MANY   = '\ext\activedocument\HasManyRelation';
    const MANY_MANY  = '\ext\activedocument\ManyManyRelation';
    const STAT       = '\ext\activedocument\StatRelation';

    /**
     * Override with component connection name, if not 'conn'
     * Is accessed using Late Static Binding (i.e. - static::$connName)
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
     * @static
     *
     * @param string $className optional
     *
     * @return \ext\activedocument\Document
     */
    public static function model($className = null) {
        if ($className === null)
            $className = get_called_class();
        if (isset(self::$_models[$className]))
            return self::$_models[$className];
        else {
            $document      = self::$_models[$className] = new $className(null);
            $document->_md = new MetaData($document);
            $document->attachBehaviors($document->behaviors());
            return $document;
        }
    }

    public function __construct($scenario = 'insert') {
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

    /**
     * The owner/parent of this Document (if any)
     *
     * @return \ext\activedocument\Document
     */
    public function getOwner() {
        return $this->_owner;
    }

    /**
     * The owner/parent of this Document (if any)
     *
     * @param \ext\activedocument\Document $owner
     */
    public function setOwner(Document $owner = null) {
        $this->_owner = $owner;
    }

    protected function newObject() {
        $this->setObject($this->loadObject());
    }

    protected function loadObject($key = null) {
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
        if (!$this->getIsNewRecord())
            $this->ensurePk();
        else
            $this->_object->data = $this->getMetaData()->attributeDefaults;
        $this->setAttributes($this->_object->data, false);
    }

    public function getIsNewRecord() {
        return $this->_new;
    }

    public function setIsNewRecord($value) {
        $this->_new = $value;
    }

    public function getCriteria($createIfNull = true) {
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
        return array_keys((array)$this);
    }

    public function &__get($name) {
        if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
        else if (isset($this->getMetaData()->attributes->$name)) {
            $return = null;
            return $return;
        } else if (isset($this->_related[$name]))
            return $this->_related[$name];
        else if (isset($this->getMetaData()->relations->$name))
            return $this->getRelated($name);
        else {
            $return = parent::__get($name);
            return $return;
        }
    }

    public function __set($name, $value) {
        if ($this->setAttribute($name, $value) === false) {
            if (isset($this->getMetaData()->relations->$name))
                $this->_related[$name] = $value;
            else
                parent::__set($name, $value);
        }
    }

    public function __isset($name) {
        if (isset($this->_attributes[$name]))
            return true;
        else if (isset($this->getMetaData()->attributes->$name))
            return false;
        else if (isset($this->_related[$name]))
            return true;
        else if (isset($this->getMetaData()->relations->$name))
            return $this->getRelated($name) !== null;
        else
            return parent::__isset($name);
    }

    public function __unset($name) {
        if (isset($this->getMetaData()->attributes->$name))
            unset($this->_attributes[$name]);
        else if (isset($this->getMetaData()->relations->$name))
            unset($this->_related[$name]);
        else
            parent::__unset($name);
    }

    public function __call($name, $parameters) {
        if (isset($this->getMetaData()->relations->$name)) {
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
     * Returns arrays of indexed keys, only applicable to HasMany or ManyMany relations
     *
     * @param string $name The relation name (see {@link relations})
     * @param string|array $index Name of the index, or array of index names
     *
     * @return array[]array[]string
     */
    public function getRelatedKeysByIndexName($name, $index) {
        $index = array_combine((array) $index, (array) $index);
        $object = $this->getObject();
        return array_map(function($index)use($name, $object){
            return isset($object->data[$name.'_'.$index])?$object->data[$name.'_'.$index]:array();
        }, $index);
    }

    /**
     * Return array of keys, filtered by specified index values, only applicable to HasMany or ManyMany relations
     *
     * @param string $name The relation name (see {@link relations})
     * @param array $indexes Array of 'indexName'=>'searchValue' to search by
     * @param array $keys Array of keys to additionally filter by
     *
     * @return array
     */
    public function getRelatedKeysByIndex($name, array $indexes, array $keys = array()) {
        $pks = array();
        $object = $this->getObject();
        $class = get_class($this);
        array_walk($indexes, function($indexValue, $index)use($name, $object, &$pks, $class){
            $indexName = $name.'_'.$index;
            if($indexValue === '' || $indexValue === null)
                return;
            $indexValue = $class::stringify($indexValue);
            if(!isset($object->data[$indexName][$indexValue]) || $object->data[$indexName][$indexValue]===array())
                return;
            $pks[] = $object->data[$indexName][$indexValue];
        });
        if($pks===array())
            return array();
        if ($keys!==array())
            array_push($pks, $keys);
        return count($pks)>1 ? call_user_func_array('array_intersect', $pks) : array_shift($pks);
    }

    /**
     * Returns related records filtered by indexed values, only applicable to HasMany or ManyMany relations
     *
     * @param string $name The relation name (see {@link relations})
     * @param array $indexes Array of 'indexName'=>'searchValue' to search by
     * @param bool $refresh Whether to force reload objects from db
     * @param array $params Additional parameters to customize query
     * @param array $keys Array of keys to additionally filter by
     *
     * @return array
     */
    public function &getRelatedByIndex($name, array $indexes, $refresh = false, array $params = array(), array $keys = array()) {
        $pks = $this->getRelatedKeysByIndex($name, $indexes, $keys);
        if ($pks === array())
            return array();
        Yii::trace('Requesting related records for relation ' . get_class($this) . '.'.$name.', filtered by '.\CVarDumper::dumpAsString($indexes), 'ext.activedocument.document.getRelatedByIndex');
        return $this->getRelated($name, $refresh, $params, $pks);
    }

    /**
     * Returns the related record(s).
     * This method will return the related record(s) of the current record.
     * If the relation is HAS_ONE or BELONGS_TO, it will return a single object
     * or null if the object does not exist.
     * If the relation is HAS_MANY or MANY_MANY, it will return an array of objects
     * or an empty array.
     *
     * @param string  $name    the relation name (see {@link relations})
     * @param boolean $refresh whether to reload the related objects from database. Defaults to false.
     * @param array   $params  additional parameters that customize the query conditions as specified in the relation declaration.
     * @param array   $keys    Array of encoded primary keys to filter by on HasMany or ManyMany relations
     *
     * @return mixed the related object(s).
     * @throws Exception if the relation is not specified in {@link relations}.
     */
    public function &getRelated($name, $refresh = false, array $params = array(), array $keys = array()) {
        if (!$refresh && $params === array() && (isset($this->_related[$name]) || array_key_exists($name, $this->_related)))
            if ($keys!==array() && is_array($this->_related[$name])) {
                return array_filter($this->_related[$name], function(Document $document)use($keys){
                    return in_array($document->getEncodedPk(), $keys);
                });
            }else{
                return $this->_related[$name];
            }

        $md = $this->getMetaData();
        if (!isset($md->relations[$name]))
            throw new Exception(Yii::t('yii', '{class} does not have relation "{name}".', array('{class}' => get_class($this), '{name}' => $name)));

        Yii::trace('lazy loading ' . get_class($this) . '.' . $name, 'ext.activedocument.document.getRelated');
        /**
         * @var \ext\activedocument\BaseRelation
         */
        $relation = $md->relations[$name];

        if ($this->getIsNewRecord() && !$refresh && ($relation instanceof HasOneRelation || $relation instanceof HasManyRelation)) {
            $_r = $relation instanceof HasOneRelation ? null : array();
            return $_r;
        }

        if ($params !== array() || ($relation instanceof HasManyRelation && $keys !== array())) { // dynamic query
            $exists = isset($this->_related[$name]) || array_key_exists($name, $this->_related);
            if ($exists)
                $save = $this->_related[$name];
        }
        unset($this->_related[$name]);

        $data = $this->getObject()->data;
        if (isset($data[$name])) {
            if ($relation instanceof Relation && $relation->nested === true && $params === array()) {
                Yii::trace('Loading nested ' . get_class($this) . '.' . $name, 'ext.activedocument.document.getRelated');
                if ($relation instanceof HasManyRelation) {
                    $this->_related[$name] = Document::model($relation->className)
                        ->populateDocuments(array_map('unserialize', $data[$name]));
                    if ($keys!==array())
                        $this->_related[$name] = array_intersect_key($this->_related[$name], array_flip($keys));
                } else
                    $this->_related[$name] = Document::model($relation->className)
                        ->populateDocument(unserialize($data[$name]));
            } else {
                if ($relation instanceof HasManyRelation) {
                    $pks = $data[$name];
                    if ($relation->nested === true)
                        $pks = array_keys($pks);
                    if ($keys !== array())
                        $pks = array_intersect($pks, $keys);
                    $this->_related[$name] = Document::model($relation->className)
                        ->findAllByPk($pks, null, $params);
                /* else if ($relation instanceof StatRelation)
             $this->_related[$name] = $relation->defaultValue; */
                } else
                    $this->_related[$name] = Document::model($relation->className)
                        ->findByPk($data[$name], null, $params);
            }
        }

        if (!isset($this->_related[$name])) {
            if ($relation instanceof HasManyRelation)
                $this->_related[$name] = array();
            else if ($relation instanceof StatRelation)
                $this->_related[$name] = $relation->defaultValue;
            else
                $this->_related[$name] = null;
        }

        if ($params !== array() || ($relation instanceof HasManyRelation && $keys !== array())) {
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
     *
     * @param string $name the relation name
     *
     * @return boolean a value indicating whether the named related object(s) has been loaded.
     */
    public function hasRelated($name) {
        return isset($this->_related[$name]) || array_key_exists($name, $this->_related);
    }

    /**
     * Used to populate related objects. This method adds a related object to this record.
     *
     * @param string         $name        attribute name
     * @param array|Document $document    the related document[s]
     * @param string         $foreignName the name of this relationship in the related document. If not empty, relation will be set both ways
     */
    public function addRelated($name, $document, $foreignName = null) {
        if ($this->getMetaData()->relations->$name instanceof HasManyRelation && !is_array($document)) {
            if (!isset($this->_related[$name]) || !is_array($this->_related[$name]))
                $this->_related[$name] = array();
            $this->_related[$name][] = $document;
        } else
            $this->_related[$name] = $document;

        if (!empty($foreignName)) {
            if (!is_array($document))
                $document = array($document);
            array_walk($document, function(Document $document, $index, array $relation) {
                list($relationName, $relatedDocument) = $relation;
                $document->addRelated($relationName, $relatedDocument);
            }, array($foreignName, $this));
        }
    }

    public function hasAttribute($name) {
        return isset($this->getMetaData()->attributes->$name);
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
        else if (isset($this->getMetaData()->attributes->$name))
            $this->_attributes[$name] = $value;
        else
            return false;
        return true;
    }

    public function getAttributes($names = true) {
        $attributes = $this->_attributes;
        foreach ($this->attributeNames() as $name) {
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
            $object         = $this->getObject();
            foreach ($this->getMetaData()->attributes as $name => $attr) {
                if (property_exists($this, $name) && isset($object->data[$name]))
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

    /**
     * Returns the propert[y|ies] used to compose the model pk
     *
     * @return string|array
     */
    public function primaryKey() {
        return '_pk';
    }

    /**
     * Returns the model's pk value
     *
     * @return mixed
     */
    public function getPrimaryKey() {
        $pk = $this->primaryKey();
        if (is_string($pk))
            return $this->{$pk};
        else {
            $isNull = true;
            $return = array();
            foreach ($pk as $pkField) {
                $isNull           = $isNull && (is_null($this->{$pkField}) || $this->{$pkField} === '');
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

    public function setPrimaryKey($value) {
        if ($this->primaryKey() !== '_pk')
            throw new Exception('Unable to store custom primary key!');
        $this->_pk = $value;
    }

    /**
     * Method to ensure that $this->_pk is defined correctly
     */
    protected function ensurePk() {
        if ($this->_pk === null)
            if ($this->primaryKey() !== '_pk' && $this->getPrimaryKey() !== null)
                $this->_pk = self::stringify($this->getPrimaryKey());
            elseif ($this->_object->getKey() !== null)
                $this->_pk = $this->_object->getKey();
    }

    /**
     * @return string
     */
    public function getEncodedPk() {
        $this->ensurePk();
        return $this->_pk;
    }

    /**
     * Self-recursive function (for arrays)
     * Takes mixed variable types
     * Returns objects/arrays as json
     * Casts any other type to string to ensure type consistency
     *
     * @param mixed $var
     *
     * @return string
     */
    public static function stringify($var) {
        if (is_array($var))
            return \CJSON::encode(array_map(array('self', 'stringify'), $var));
        if (is_object($var))
            return \CJSON::encode($var);
        return (string)$var;
    }

    /* public function cache($duration, $dependency=null, $queryCount=1) {
      $this->getDbConnection()->cache($duration, $dependency, $queryCount);
      return $this;
      } */

    /**
     * @todo Implement support for indexing at bucket level
     * @return array
     */
    public function indexes() {
        return array();
    }

    public function relations() {
        return array();
    }

    public function scopes() {
        return array();
    }

    public function attributeNames() {
        return array_keys((array)$this->getMetaData()->attributes);
    }

    /**
     * Defines validation ruleset for models, override to prevent automatic rule generation.
     *
     * @todo Define rules based on attribute types
     *
     * @return array
     */
    public function rules() {
        return array_merge(parent::rules(), array(
            array(implode(', ', $this->attributeNames()), 'safe', 'on' => 'search'),
        ));
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param array $attributes Array of attribute names to limit searching to
     *
     * @return \ext\activedocument\DataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search(array $attributes = array()) {
        $criteria = new Criteria;

        $attributes = array_intersect_key((array)$this->getMetaData()->attributes, array_flip(!empty($attributes) ? $attributes : $this->getSafeAttributeNames()));
        foreach ($attributes as $name => $attribute) {
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

        if (array_key_exists(static::$connName, self::$connections) && self::$connections[static::$connName] instanceof Connection)
            return self::$connections[static::$connName];
        else {
            self::$connections[static::$connName] = Yii::app()->getComponent(static::$connName);
            if (self::$connections[static::$connName] instanceof Connection)
                return self::$connections[static::$connName];
            else
                throw new Exception(Yii::t('yii', 'Active Document requires a "' . static::$connName . '" Connection application component.'));
        }
    }

    /**
     * @return \ext\activedocument\Adapter
     */
    public function getAdapter() {
        return $this->getConnection()->getAdapter();
    }

    /**
     * @return string
     */
    public function containerName() {
        return get_class($this);
    }

    /**
     * @return string
     */
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

    /**
     * @return array
     */
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

    /**
     * @param array|null $attributes  optional
     * @param bool       $clearErrors optional
     *
     * @return bool
     */
    protected function internalValidate(array $attributes = null, $clearErrors = true) {
        return parent::validate($attributes, $clearErrors);
    }

    /**
     * Validate main model and all it's related models recursively.
     *
     * @param string|array $data        attribute[s] and/or relation[s].
     * @param boolean      $clearErrors whether to call {@link CModel::clearErrors} before performing validation.
     *
     * @return boolean whether the validation is successful without any error.
     */
    public function validate($data = null, $clearErrors = true) {
        if ($data === null) {
            $attributes = null;
            $newData    = array();
        } else {
            if (is_string($data))
                $data = array($data);
            $attributeNames = $this->attributeNames();
            $attributes     = array_intersect($data, $attributeNames);

            if ($attributes === array())
                $attributes = null;

            $newData = array_diff($data, $attributeNames);
        }

        $valid = $this->internalValidate($attributes, $clearErrors);

        foreach ($newData as $name => $data) {
            if (!is_array($data))
                $name = $data;

            if (!$this->hasRelated($name))
                continue;

            $related = $this->getRelated($name);

            if (is_array($related)) {
                foreach ($related as $model) {
                    $valid = $model->validate(is_array($data) ? $data : null, $clearErrors) && $valid;
                }
            } else {
                $valid = $related->validate(is_array($data) ? $data : null, $clearErrors) && $valid;
            }
        }

        return $valid;
    }

    /**
     * Saves model and it's relation info
     *
     * @param bool         $runValidation whether to perform validation before saving the record.
     * @param string|array $attributes    attribute[s] to be validated/saved
     *
     * @return boolean whether the saving succeeds.
     */
    public function save($runValidation = true, $attributes = null) {
        if (!$runValidation || $this->validate($attributes))
            return $this->saveInternal($attributes);
        else
            return false;
    }

    /**
     * Internal mechanism for recursively saving model & relations
     *
     * @param array $attributes     Array of attributes to save
     * @param array $modelRelations Registry of models that have been processed, to avoid endless recursion
     *
     * @return boolean True on success
     */
    protected function saveInternal(array $attributes = null, &$modelRelations = array()) {
        if ($attributes === array())
            $attributes = null;

        $relations = $this->getMetaData()->relations;
        $queue     = array();

        foreach ($relations as $name => $relation) {
            /**
             * Only process this relation if it has been loaded (even loaded & unset)
             */
            if (!$this->hasRelated($name))
                continue;

            if ($relation instanceof BelongsToRelation) {
                $relationHash = array(get_class($this), $relation->className);
                sort($relationHash);
                /**
                 * Ensure relation hasn't already been processed
                 */
                if (in_array($relationHash, $modelRelations))
                    continue;
                array_push($modelRelations, $relationHash);

                $related = $this->getRelated($name);

                /**
                 * If the relation is empty...
                 */
                if ($related === null) {
                    /**
                     * If the relation was already empty, skip
                     */
                    if (!isset($this->getObject()->data[$name]) || $this->getObject()->data[$name] === null || $this->getObject()->data[$name] === '')
                        continue;

                    /**
                     * If the relation wasn't already empty, then it should be removed
                     */
                    Yii::trace('Removing a BELONGS_TO relation in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.' . get_class($this));
                    $this->clearRelation($name);
                    continue;
                }

                /**
                 * If the relation was already set, skip
                 */
                if (isset($this->getObject()->data[$name]) && !$related->getIsNewRecord() && $related->getPrimaryKey() === $this->getObject()->data[$name])
                    continue;

                Yii::trace('Saving a BELONGS_TO relation in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.' . get_class($this));

                /**
                 * Ensure $related is saved, so we have current PK
                 */
                if ($related->saveInternal(null, $modelRelations))
                    $this->appendRelation($related, $name);
            }
            else
                $queue[] = $name;
        }

        if ($this->getIsNewRecord() && empty($this->primaryKey))
            if (!$this->insert($attributes))
                return false;
            elseif (empty($queue))
                return true;

        /**
         * @todo May need to separate saving from the process, until the end, to prevent repetitive saving
         */
        foreach ($queue as $name) {
            $relationHash = array(get_class($this), $relations[$name]->className);
            sort($relationHash);
            /**
             * Ensure relation hasn't already been processed
             */
            if (in_array($relationHash, $modelRelations))
                continue;
            array_push($modelRelations, $relationHash);

            $related = $this->getRelated($name);

            /**
             * If the relation is empty...
             */
            if ($related === null || $related === array()) {
                /**
                 * If the relation was already empty, skip
                 */
                if (!isset($this->getObject()->data[$name]) || $this->getObject()->data[$name] === null || $this->getObject()->data[$name] === array())
                    continue;

                /**
                 * If the relation wasn't already empty, then it should be removed
                 */
                Yii::trace('Removing a ' . get_class($relations[$name]) . ' in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.' . get_class($this));
                $this->clearRelation($name);
                continue;
            }

            if ($relations[$name] instanceof HasManyRelation) {
                foreach ($related as $model) {
                    /**
                     * If the relation was already set, skip
                     */
                    if (isset($this->getObject()->data[$name]) && !$model->getIsNewRecord() && in_array($model->getPrimaryKey(), $this->getObject()->data[$name]))
                        continue;

                    $model->saveInternal(null, $modelRelations);

                    Yii::trace('Saving a HAS_MANY/MANY_MANY relation in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.' . get_class($this));
                    $this->appendRelation($model, $name);
                }
            } else {
                /**
                 * If the relation was already set, skip
                 */
                if (isset($this->getObject()->data[$name]) && !$related->getIsNewRecord() && $related->getPrimaryKey() === $this->getObject()->data[$name])
                    continue;

                $related->saveInternal(null, $modelRelations);

                Yii::trace('Saving a HAS_ONE relation in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.' . get_class($this));
                $this->appendRelation($related, $name);
            }
        }

        if (!($this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes)))
            return false;

        return true;
    }

    /**
     * Pushes the $relationModel's PK into current object's relations
     *
     * @param Document $relationModel
     * @param string   $relationName
     *
     * @throws Exception
     */
    public function appendRelation(Document $relationModel, $relationName) {
        $pk = $relationModel->getPrimaryKey();
        if (empty($pk))
            throw new Exception(Yii::t('yii', 'Related model primary key must not be empty!'));

        /**
         * @var \ext\activedocument\Relation
         */
        $relation = $this->getMetaData()->relations->$relationName;

        if ($relation instanceof HasManyRelation) {
            /**
             * Manages relation indexes stored within the model
             */
            if ($relation->autoIndices !== array()) {
                foreach($relation->autoIndices as $index) {
                    $indexName = $relationName.'_'.$index;
                    $indexValue = $relationModel->getAttribute($index);
                    if($indexValue === '' || $indexValue === null)
                        continue;
                    $indexValue = self::stringify($indexValue);
                    if (!isset($this->getObject()->data[$indexName]) || !is_array($this->getObject()->data[$indexName]))
                        $this->getObject()->data[$indexName] = array();
                    if (!isset($this->getObject()->data[$indexName][$indexValue]) || !is_array($this->getObject()->data[$indexName][$indexValue]))
                        $this->getObject()->data[$indexName][$indexValue] = array();
                    $this->getObject()->data[$indexName][$indexValue][] = $pk;
                }
            }
            if (!isset($this->getObject()->data[$relationName]) || !is_array($this->getObject()->data[$relationName]))
                $this->getObject()->data[$relationName] = array();
            if ($relation->nested === true) {
                if (!isset($this->getObject()->data[$relationName][$relationModel->getEncodedPk()]))
                    $this->getObject()->data[$relationName][$relationModel->getEncodedPk()] = serialize($relationModel->getObject());
            } elseif (!in_array($pk, $this->getObject()->data[$relationName]))
                $this->getObject()->data[$relationName][] = $pk;
        } else
            $this->getObject()->data[$relationName] = $relation->nested ? serialize($relationModel->getObject()) : $pk;
    }

    /**
     * Removes the $relationModel's PK from current object's relations
     *
     * @param Document $relationModel
     * @param string   $relationName
     *
     * @throws Exception
     */
    public function removeRelation(Document $relationModel, $relationName) {
        if (!isset($this->getObject()->data[$relationName]))
            return;

        $pk = $relationModel->getPrimaryKey();
        if (empty($pk))
            throw new Exception(Yii::t('yii', 'Related model primary key must not be empty!'));

        /**
         * @var \ext\activedocument\Relation
         */
        $relation = $this->getMetaData()->relations->$relationName;

        if ($relation instanceof HasManyRelation && is_array($this->getObject()->data[$relationName])) {
            $key = $relation->nested ? $relationModel->getEncodedPk() : array_search($pk, $this->getObject()->data[$relationName]);
            if ($key !== false)
                unset($this->getObject()->data[$relationName][$key]);
        } else
            unset($this->getObject()->data[$relationName]);
    }

    /**
     * Empties any object associations for the specified relation
     *
     * @param string $relationName
     */
    public function clearRelation($relationName) {
        unset($this->getObject()->data[$relationName]);
    }

    public function onBeforeSave(Event $event) {
        $this->raiseEvent('onBeforeSave', $event);
    }

    public function onAfterSave(\CEvent $event) {
        $this->raiseEvent('onAfterSave', $event);
    }

    public function onBeforeDelete(Event $event) {
        $this->raiseEvent('onBeforeDelete', $event);
    }

    public function onAfterDelete(\CEvent $event) {
        $this->raiseEvent('onAfterDelete', $event);
    }

    public function onBeforeFind(Event $event) {
        $this->raiseEvent('onBeforeFind', $event);
    }

    public function onAfterFind(\CEvent $event) {
        $this->raiseEvent('onAfterFind', $event);
    }

    protected function beforeSave() {
        if ($this->hasEventHandler('onBeforeSave')) {
            $event = new Event($this);
            $this->onBeforeSave($event);
            return $event->isValid;
        }
        else
            return true;
    }

    protected function afterSave() {
        if ($this->hasEventHandler('onAfterSave'))
            $this->onAfterSave(new \CEvent($this));
    }

    protected function beforeDelete() {
        if ($this->hasEventHandler('onBeforeDelete')) {
            $event = new Event($this);
            $this->onBeforeDelete($event);
            return $event->isValid;
        }
        else
            return true;
    }

    protected function afterDelete() {
        if ($this->hasEventHandler('onAfterDelete'))
            $this->onAfterDelete(new \CEvent($this));
    }

    protected function beforeFind() {
        if ($this->hasEventHandler('onBeforeFind')) {
            $event = new Event($this);
            // for backward compatibility
            $event->criteria = func_num_args() > 0 ? func_get_arg(0) : null;
            $this->onBeforeFind($event);
        }
    }

    protected function afterFind() {
        if ($this->hasEventHandler('onAfterFind'))
            $this->onAfterFind(new \CEvent($this));
    }

    public function beforeFindInternal() {
        $this->beforeFind();
    }

    public function afterFindInternal() {
        $this->afterFind();
    }

    protected function store(array $attributes = null) {
        $attributes = $this->getAttributes($attributes);
        foreach ($attributes as $name => $value) {
            $this->_object->data[$name] = $value;
        }
        $this->_object->setKey($this->_pk);
        return $this->_object->store();
    }

    /**
     * @param array|null $attributes optional
     *
     * @return bool
     * @throws Exception
     */
    public function insert(array $attributes = null) {
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

    /**
     * @param array|null $attributes optional
     *
     * @return bool
     * @throws Exception
     */
    public function update(array $attributes = null) {
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

    /**
     * @param array $attributes
     *
     * @return bool
     * @throws Exception
     */
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

    /**
     * @return bool
     * @throws Exception
     */
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

    public function count($condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.count()', 'ext.activedocument.' . get_class($this));
        $criteria = $this->buildCriteria($condition, $params);
        $this->applyScopes($criteria);
        return $this->_container->count($criteria);
    }

    public function find($condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.find()', 'ext.activedocument.' . get_class($this));
        return $this->query($this->buildCriteria($condition, $params));
    }

    /**
     * @param string|int|array $key
     *
     * @return \ext\activedocument\Document
     */
    public function findByPk($key, $condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.findByPk()', 'ext.activedocument.' . get_class($this));
        return $this->query($this->buildCriteria($condition, $params), false, array($key));
    }

    public function findAll($condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.findAll()', 'ext.activedocument.' . get_class($this));
        return $this->query($this->buildCriteria($condition, $params), true);
    }

    public function findAllByPk(array $keys, $condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.findAllByPk()', 'ext.activedocument.' . get_class($this));
        return $this->query($this->buildCriteria($condition, $params), true, $keys);
    }

    protected function query($criteria, $all = false, array $keys = array()) {
        $this->beforeFind();
        $this->applyScopes($criteria);

        if (!empty($keys))
            $keys = array_map(array('self', 'stringify'), $keys);

        $objects       = array();
        $emptyCriteria = new Criteria;
        if ($criteria == $emptyCriteria && !empty($keys)) {
            /**
             * @todo Need to implement getObjects to speed up this process
             * $objects = $this->getContainer()->getObjects($keys);
             */
            foreach ($keys as $key) {
                /**
                 * @todo This is temporary fix for issue where empty object is returned...
                 */
                $obj = $this->loadObject($key);
                if (!empty($obj->objectData))
                    $objects[] = $obj;
            }
        } else {
            if (!$all)
                $criteria->limit = 1;
            if (!empty($keys))
                foreach ($keys as $key)
                    $criteria->addInput($this->containerName(), $key);
            $objects = $this->getContainer()->find($criteria);
        }

        if (empty($objects))
            return $all ? array() : null;

        return $all ? $this->populateDocuments($objects) : $this->populateDocument(array_shift($objects));
    }

    protected function buildCriteria($condition, $params = array()) {
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
     *
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
     * @param bool   $callAfterFind
     *
     * @return \ext\activedocument\Document
     */
    public function populateDocument(Object $object, $callAfterFind = true) {
        $document = $this->instantiate($object);
        $document->setScenario('update');
        $document->setObject($object);
        $document->init();
        $document->attachBehaviors($document->behaviors());
        if ($callAfterFind)
            $document->afterFind();
        return $document;
    }

    public function populateDocuments(array $objects, $callAfterFind = true, $index = null) {
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
     *
     * @return \ext\activedocument\Document
     */
    protected function instantiate(Object $object) {
        $class    = get_class($this);
        $document = new $class(null);
        return $document;
    }

    public function offsetExists($offset) {
        return $this->__isset($offset);
    }

}